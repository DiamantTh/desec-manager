<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * SecurityHeadersMiddleware — sets HTTP security headers for all responses.
 *
 * Protects against:
 *   - Clickjacking        → X-Frame-Options: DENY
 *   - MIME-Sniffing       → X-Content-Type-Options: nosniff
 *   - XSS (legacy)        → X-XSS-Protection
 *   - Referrer-Leakage    → Referrer-Policy
 *   - HTTPS-Downgrade     → HSTS (1 Jahr)
 *   - Inline-Scripts/XSS  → Content-Security-Policy
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $csp = implode(' ', [
            "default-src 'self';",
            "script-src 'self';",
            "style-src 'self' 'unsafe-inline';",
            "img-src 'self' data:;",
            "font-src 'self';",
            "connect-src 'self';",
            "frame-ancestors 'none';",
            "base-uri 'self';",
            "form-action 'self';",
        ]);

        return $response
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload')
            ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()')
            ->withHeader('Content-Security-Policy', $csp);
    }
}
