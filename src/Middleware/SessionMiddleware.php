<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * SessionMiddleware — konfiguriert PHP-Session-Cookie-Flags.
 *
 * Läuft VOR Mezzio\Session\SessionMiddleware und setzt die Cookie-Parameter,
 * bevor die mezzio/mezzio-session-ext-Schicht die Session startet (session_start).
 *
 * Konfiguration aus security.toml [security.session]:
 *   secure   = true       → Nur HTTPS
 *   httponly = true       → Kein JS-Zugriff
 *   samesite = "Strict"   → CSRF-Schutz
 *   lifetime = 3600       → Session-Lebensdauer
 *
 * HINWEIS: session_start() wird NICHT hier aufgerufen — das übernimmt
 * Mezzio\Session\Ext\PhpSessionPersistence nach dieser Middleware.
 */
class SessionMiddleware implements MiddlewareInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var array<string, mixed> $sessionCfg */
        $sessionCfg = $this->config['security']['session'] ?? [];

        $rawSamesite = strtolower((string)($sessionCfg['samesite'] ?? 'strict'));
        $samesite    = match ($rawSamesite) {
            'lax'  => 'Lax',
            'none' => 'None',
            default => 'Strict',
        };

        // Cookie-Flags setzen BEVOR mezzio-session-ext session_start() aufruft.
        // session_start() selbst wird von Mezzio\Session\Ext\PhpSessionPersistence übernommen.
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => (int)($sessionCfg['lifetime']  ?? 3600),
                'path'     => '/',
                'domain'   => '',
                'secure'   => (bool)($sessionCfg['secure']   ?? true),
                'httponly' => (bool)($sessionCfg['httponly']  ?? true),
                'samesite' => $samesite,
            ]);
        }

        return $handler->handle($request);
    }
}
