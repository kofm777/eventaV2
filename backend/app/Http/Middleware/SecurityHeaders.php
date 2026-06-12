<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 5 hardening — sets baseline security response headers on every API response.
 *
 * Purely ADDITIVE: it only adds headers, never touches the body, status, or routing,
 * so every existing flow is byte-for-byte unchanged. Appended last in the 'api'
 * middleware group (after ResolveOrganizer). Preferred over nginx headers because it
 * ships with the app and covers the Railway deploy without editing nginx.conf.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-XSS-Protection' => '1; mode=block',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        foreach ($headers as $name => $value) {
            // Do not clobber a header an upstream/proxy may have already set.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
