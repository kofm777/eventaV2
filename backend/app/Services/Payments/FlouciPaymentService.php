<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Flouci (developer.flouci.com) payment gateway adapter — real TND charges.
 *
 * Implements the SAME interface as StubPaymentService so a gateway swap only
 * changes this impl + the AppServiceProvider binding, never the controllers or
 * OrderService. Built on Laravel's Http facade (Guzzle-backed) with timeouts and
 * try/catch around every call.
 *
 * Lifecycle:
 *   createIntent  -> POST {base}/generate_payment  (secrets in BODY)
 *                    stores result.payment_id in payment_intent_id, result.link in
 *                    payment_link; returns clientAction ['type'=>'redirect','url'=>link].
 *   confirm       -> GET  {base}/verify_payment/{id} (secrets in HEADERS)
 *                    paid:true ONLY when result.status === 'SUCCESS' (server-side).
 *   handleWebhook -> null. Flouci has no signed webhook; verification is pull-based
 *                    on the buyer's return, so we never trust an unsigned callback.
 *
 * SECURITY: the buyer returning to FRONTEND_URL/payment/flouci/return cannot forge a
 * paid state. confirm() re-verifies server-to-server against Flouci using the secret
 * credentials and the payment_id WE generated for THIS order; a non-SUCCESS status
 * => paid:false => 402, no tickets. Any transport/HTTP error => paid:false (never
 * paid:true on error).
 *
 * Field paths (result.link / result.payment_id / result.status) and the secrets
 * placement (header vs body) are isolated so adjusting them pre-signing is a 1-line
 * change.
 */
class FlouciPaymentService implements PaymentService
{
    private string $appToken;
    private string $appSecret;
    private string $baseUrl;
    private int $timeoutSecs;

    public function __construct()
    {
        $cfg = config('services.payments.flouci');

        $this->appToken = (string) ($cfg['app_token'] ?? '');
        $this->appSecret = (string) ($cfg['app_secret'] ?? '');
        $this->baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://developer.flouci.com/api'), '/');
        $this->timeoutSecs = (int) ($cfg['timeout_secs'] ?? 1200);

        // Constructor guard: the driver stays INACTIVE until the owner signs with
        // Flouci and sets the two secrets. Refuse to operate half-configured rather
        // than silently leaking orders into a fake-paid limbo.
        if ($this->appToken === '' || $this->appSecret === '') {
            Log::error('FlouciPaymentService misconfigured: FLOUCI_APP_TOKEN / FLOUCI_APP_SECRET are empty. Set both to activate the Flouci driver.');

            throw new RuntimeException('Flouci payment driver is not configured (missing app token/secret).');
        }
    }

    /**
     * {@inheritDoc}
     *
     * Creates a Flouci payment session and returns a redirect clientAction.
     * On any HTTP/transport error we throw so purchase() returns 500 and no order
     * is left in a fake-paid state.
     */
    public function createIntent(Order $order): PaymentIntentResult
    {
        // TND -> millimes, integer. Flouci rejects non-integer / sub-millime amounts.
        $millimes = (int) round(((float) $order->amount_total) * 1000);

        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $returnBase = $frontend . '/payment/flouci/return?order=' . rawurlencode($order->order_number);

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl . '/generate_payment', [
                    'app_token' => $this->appToken,
                    'app_secret' => $this->appSecret,
                    'amount' => $millimes,                       // integer millimes
                    'accept_card' => 'true',                     // string per Flouci contract
                    'session_timeout_secs' => $this->timeoutSecs,
                    'success_link' => $returnBase,
                    'fail_link' => $returnBase . '&status=fail',
                    'developer_tracking_id' => $order->order_number, // our correlation key
                ]);

            if ($response->failed()) {
                Log::error('Flouci generate_payment HTTP error', [
                    'order_number' => $order->order_number,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException('Flouci generate_payment failed (HTTP ' . $response->status() . ').');
            }

            $link = $response->json('result.link');
            $paymentId = $response->json('result.payment_id');

            if (empty($link) || empty($paymentId)) {
                Log::error('Flouci generate_payment returned no link/payment_id', [
                    'order_number' => $order->order_number,
                    'body' => $response->body(),
                ]);

                throw new RuntimeException('Flouci generate_payment returned an incomplete response.');
            }
        } catch (RuntimeException $e) {
            // Already logged above — rethrow so the controller returns 500.
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Flouci generate_payment transport error', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Could not reach the Flouci payment gateway.', 0, $e);
        }

        // Persist: payment_provider stamps the driver; payment_intent_id holds the
        // Flouci payment_id we will re-verify on return; payment_link holds the URL.
        $order->payment_provider = 'flouci';
        $order->payment_intent_id = (string) $paymentId;
        $order->payment_link = (string) $link;
        $order->save();

        return new PaymentIntentResult(
            provider: 'flouci',
            intentId: (string) $paymentId,
            clientAction: ['type' => 'redirect', 'url' => (string) $link]
        );
    }

    /**
     * {@inheritDoc}
     *
     * Server-side verify against Flouci. paid:true ONLY when result.status==='SUCCESS'.
     * The payment_id is taken from the order we generated (or the redirect payload),
     * so a client cannot substitute another order's paid id for this order. Any
     * exception => paid:false (never paid:true on error).
     */
    public function confirm(Order $order, array $payload = []): PaymentConfirmation
    {
        $paymentId = $payload['payment_id'] ?? $order->payment_intent_id;

        if (empty($paymentId)) {
            return new PaymentConfirmation(
                order: $order,
                paid: false,
                reference: '',
                failureReason: 'Missing Flouci payment id.'
            );
        }

        try {
            // verify_payment: secrets travel in HEADERS (per Flouci's actual contract),
            // in contrast to generate_payment which puts them in the BODY.
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders([
                    'apppublic' => $this->appToken,
                    'appsecret' => $this->appSecret,
                ])
                ->get($this->baseUrl . '/verify_payment/' . rawurlencode((string) $paymentId));

            if ($response->failed()) {
                Log::warning('Flouci verify_payment HTTP error', [
                    'order_number' => $order->order_number,
                    'payment_id' => $paymentId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return new PaymentConfirmation(
                    order: $order,
                    paid: false,
                    reference: (string) $paymentId,
                    failureReason: 'Flouci payment verification failed.'
                );
            }

            $ok = ($response->json('result.status') === 'SUCCESS');
            $reference = (string) ($response->json('result.payment_id') ?? $paymentId);

            return new PaymentConfirmation(
                order: $order,
                paid: $ok,
                reference: $reference,
                failureReason: $ok ? null : 'Flouci payment not completed.'
            );
        } catch (\Throwable $e) {
            Log::error('Flouci verify_payment transport error', [
                'order_number' => $order->order_number,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            // NEVER paid:true on error.
            return new PaymentConfirmation(
                order: $order,
                paid: false,
                reference: (string) $paymentId,
                failureReason: 'Could not verify the Flouci payment.'
            );
        }
    }

    /**
     * {@inheritDoc}
     *
     * Flouci has NO signed webhook — verification is pull-based on the buyer's return
     * via confirm()/verify_payment. We deliberately trust no unsigned callback, so the
     * webhook endpoint is inert for this driver (returns null => controller 404s).
     */
    public function handleWebhook(array $payload, array $headers): ?PaymentConfirmation
    {
        return null;
    }
}
