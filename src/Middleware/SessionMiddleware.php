<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * SessionMiddleware — starts the PHP session with secure cookie flags.
 *
 * Configuration from security.toml [security.session]:
 *   secure   = true       → HTTPS only
 *   httponly = true       → no JS access
 *   samesite = "Strict"   → CSRF protection
 *   lifetime = 3600       → session lifetime
 */
class SessionMiddleware implements MiddlewareInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() === PHP_SESSION_NONE) {
            /** @var array<string, mixed> $sessionCfg */
            $sessionCfg = $this->config['security']['session'] ?? [];

            $rawSamesite = strtolower((string)($sessionCfg['samesite'] ?? 'strict'));
            $samesite    = match ($rawSamesite) {
                'lax'  => 'Lax',
                'none' => 'None',
                default => 'Strict',
            };

            session_set_cookie_params([
                'lifetime' => (int)($sessionCfg['lifetime']  ?? 3600),
                'path'     => '/',
                'domain'   => '',
                'secure'   => (bool)($sessionCfg['secure']   ?? true),
                'httponly' => (bool)($sessionCfg['httponly']  ?? true),
                'samesite' => $samesite,
            ]);

            session_start();
            
            // Initialize translator after session start
            \App\Service\Translator::init(dirname(__DIR__, 2) . '/locale');
        }

        return $handler->handle($request);
    }
}
