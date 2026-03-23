<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * SentryMiddleware — optionales Fehler-Tracking via Sentry SDK.
 *
 * Wird nur aktiv, wenn `sentry.dsn` in der Konfiguration gesetzt ist.
 * Fängt alle nicht behandelten Throwables ab, sendet sie an Sentry
 * und wirft sie anschließend weiter (PSR-15-konform: kein Schlucken).
 *
 * Positionierung in der Pipeline: ganz außen (Schicht 1), damit auch
 * Fehler aus SecurityHeadersMiddleware, SessionMiddleware usw. erfasst werden.
 */
class SentryMiddleware implements MiddlewareInterface
{
    private bool $active = false;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $dsn = (string) ($config['sentry']['dsn'] ?? '');
        if ($dsn !== '') {
            \Sentry\init([
                'dsn'              => $dsn,
                'environment'      => (string) ($config['app']['environment'] ?? 'production'),
                'release'          => (string) ($config['app']['version'] ?? ''),
                'traces_sample_rate' => (float) ($config['sentry']['traces_sample_rate'] ?? 0.0),
            ]);
            $this->active = true;
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->active) {
            return $handler->handle($request);
        }

        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            \Sentry\captureException($e);
            throw $e;
        }
    }
}
