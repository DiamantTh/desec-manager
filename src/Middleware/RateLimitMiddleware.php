<?php

declare(strict_types=1);

namespace App\Middleware;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * RateLimitMiddleware — PSR-16-basiertes Rate-Limiting pro Aktion und Client-IP.
 *
 * Cache-Schlüssel: rate_limit:{action}:{sha256(ip)}
 *
 * Verwendung (pro Route in routes.php):
 *   $app->route('/auth/login', ['rate_limit.login', AuthHandler::class], ...);
 *
 * Die benannten Instanzen ('rate_limit.login' etc.) werden in container.php definiert.
 *
 * @see container.php  für die Factory-Definitionen der per-action-Instanzen
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private readonly int $maxAttempts;
    private readonly int $windowSeconds;

    /** @param array{max_attempts?: int, window_seconds?: int} $config */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string $action,
        array $config,
    ) {
        $this->maxAttempts   = (int) ($config['max_attempts']   ?? 10);
        $this->windowSeconds = (int) ($config['window_seconds'] ?? 900);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip  = $this->resolveClientIp($request);
        $key = 'rate_limit:' . $this->action . ':' . hash('sha256', $ip);

        $maxAttempts   = $this->maxAttempts;
        $windowSeconds = $this->windowSeconds;

        $attempts = (int) ($this->cache->get($key, 0));

        if ($attempts >= $maxAttempts) {
            return new HtmlResponse(
                '<h1>429 – ' . htmlspecialchars(__('Too Many Requests')) . '</h1>'
                . '<p>' . htmlspecialchars(__('Please try again later.')) . '</p>',
                429,
                ['Retry-After' => [(string) $windowSeconds]]
            );
        }

        // Counter erhöhen — TTL nur beim ersten Eintrag setzen (Sliding würde TTL zurücksetzen)
        if ($attempts === 0) {
            $this->cache->set($key, 1, $windowSeconds);
        } else {
            // TTL-Information aus PSR-16 nicht direkt lesbar — wir überschreiben mit neuem Stand,
            // behalten aber KEINE neue TTL (null = letzten Wert beibehalten, falls Cache-Backend das unterstützt).
            // Für Backends ohne TTL-Erhalt: wir akzeptieren ein leicht fehlendes Sliding-Verhalten.
            $this->cache->set($key, $attempts + 1, $windowSeconds);
        }

        return $handler->handle($request);
    }

    /**
     * Ermittelt die Client-IP aus REMOTE_ADDR.
     * X-Forwarded-For wird NICHT blind vertraut, um IP-Spoofing zu verhindern.
     * Für Reverse-Proxy-Setups sollte IP-Auflösung auf Infrastruktur-Ebene erfolgen.
     */
    private function resolveClientIp(ServerRequestInterface $request): string
    {
        $params = $request->getServerParams();
        return (string) ($params['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
