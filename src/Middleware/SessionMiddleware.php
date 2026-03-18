<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * SessionMiddleware — startet die PHP-Session mit sicheren Cookie-Flags.
 *
 * Konfiguration aus security.toml [security.session]:
 *   secure   = true       → nur HTTPS
 *   httponly = true       → kein JS-Zugriff
 *   samesite = "Strict"   → CSRF-Schutz
 *   lifetime = 3600       → Session-Gültigkeitsdauer
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
        }

        return $handler->handle($request);
    }
}
