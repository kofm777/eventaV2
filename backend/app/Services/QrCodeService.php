<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    private string $hmacSecret;

    private const PLACEHOLDER_SECRET = 'your-very-long-random-secret-key-for-qr-code-signing';

    public function __construct()
    {
        $this->hmacSecret = config('app.qr_hmac_secret');

        if (empty($this->hmacSecret)) {
            throw new \RuntimeException('QR_HMAC_SECRET not configured');
        }

        if ($this->hmacSecret === self::PLACEHOLDER_SECRET) {
            throw new \RuntimeException('QR_HMAC_SECRET is set to the placeholder value; set a real random secret.');
        }

        if (strlen($this->hmacSecret) < 32) {
            throw new \RuntimeException('QR_HMAC_SECRET must be at least 32 characters long.');
        }
    }

    /**
     * Generate QR code with HMAC signature
     */
    public function generateQrCode(array $payload): array
    {
        // Convert payload to JSON
        $payloadJson = json_encode($payload);
        
        // Generate HMAC signature
        $signature = hash_hmac('sha256', $payloadJson, $this->hmacSecret);
        
        // Create base64url encoded strings
        $encodedPayload = $this->base64UrlEncode($payloadJson);
        $encodedSignature = $this->base64UrlEncode($signature);
        
        // Combine payload and signature
        $qrToken = $encodedPayload . '.' . $encodedSignature;
        
        // Generate QR code image (300x300 PNG)
        $qrImage = QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->generate($qrToken);
        
        // Convert to base64 for embedding
        $qrImageBase64 = base64_encode($qrImage);
        
        return [
            'token' => $qrToken,
            'qr_image' => $qrImageBase64,
            'payload' => $payload,
        ];
    }

    /**
     * Verify QR code signature and decode payload
     */
    public function verifyQrCode(string $qrToken): array
    {
        try {
            // Split token into payload and signature
            $parts = explode('.', $qrToken);
            
            if (count($parts) !== 2) {
                throw new \Exception('Invalid QR token format');
            }
            
            [$encodedPayload, $encodedSignature] = $parts;
            
            // Decode payload and signature
            $payloadJson = $this->base64UrlDecode($encodedPayload);
            $providedSignature = $this->base64UrlDecode($encodedSignature);
            
            // Generate expected signature
            $expectedSignature = hash_hmac('sha256', $payloadJson, $this->hmacSecret);
            
            // Verify signature using constant-time comparison
            if (!hash_equals($expectedSignature, $providedSignature)) {
                Log::warning('QR code signature verification failed', [
                    'token' => substr($qrToken, 0, 50) . '...',
                ]);
                throw new \Exception('Invalid QR code signature');
            }
            
            // Decode payload
            $payload = json_decode($payloadJson, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON payload');
            }
            
            return [
                'valid' => true,
                'payload' => $payload,
            ];
            
        } catch (\Exception $e) {
            Log::warning('QR code verification failed', [
                'error' => $e->getMessage(),
                'token' => substr($qrToken, 0, 50) . '...',
            ]);
            
            return [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Whether a decoded payload carries an `exp` (unix timestamp) that is now past.
     *
     * Back-compatible: v1 participant payloads have no `exp` key, so this returns
     * false for them (they never expire via the QR layer). Used by the scanner as a
     * cheap pre-check; the ticket row's expires_at remains the durable source of truth.
     *
     * @param array<string, mixed> $payload
     */
    public function isExpired(array $payload): bool
    {
        if (! isset($payload['exp'])) {
            return false;
        }

        return time() > (int) $payload['exp'];
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
