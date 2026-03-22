<?php

declare(strict_types=1);

namespace App\Handler;

use App\Service\ThemeManager;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * AbstractHandler — gemeinsame Basis für alle PSR-15-Handler des DeSEC Managers.
 *
 * Stellt bereit:
 *   - render()        → PHP-Template aus ThemeManager laden → HtmlResponse
 *   - redirect()      → RedirectResponse
 *   - flash()         → Flash-Nachricht in Session setzen
 *   - consumeFlash()  → Flash-Nachricht lesen + aus Session löschen
 *   - userId()        → aktuell eingeloggter User (int, 0 wenn nicht eingeloggt)
 *   - isAdmin()       → Admin-Flag aus Session
 */
abstract class AbstractHandler
{
    public function __construct(protected readonly ThemeManager $theme)
    {
    }

    // -------------------------------------------------------------------------
    // Template-Rendering
    // -------------------------------------------------------------------------

    /**
     * Rendert ein PHP-Template über ThemeManager (3-stufige Fallback-Auflösung).
     *
     * @param array<string, mixed> $data  Template-Variablen
     */
    protected function render(string $template, array $data = []): ResponseInterface
    {
        $path = $this->theme->resolveTemplate($template);
        if (!file_exists($path)) {
            return new HtmlResponse(
                '<h1>500 – Template nicht gefunden</h1><p>' . htmlspecialchars($template) . '</p>',
                500
            );
        }

        $data['theme'] = $this->theme;

        return new HtmlResponse($this->renderToString($path, $data));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderToString(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }

    /**
     * Rendert einen optionalen Theme-Partial inline (gibt direkt aus).
     * Wird im Template als $this->renderPartial(...) aufgerufen.
     * Gibt nichts aus, wenn kein Partial existiert (Extension-Point-Semantik).
     *
     * @param array<string, mixed> $data  Zusätzliche Variablen für das Partial
     */
    public function renderPartial(string $partial, array $data = []): void
    {
        $path = $this->theme->resolvePartial($partial);
        if ($path === null) {
            return;
        }
        $data['theme'] = $this->theme;
        extract($data, EXTR_SKIP);
        require $path;
    }

    // -------------------------------------------------------------------------
    // HTTP-Hilfsmethoden
    // -------------------------------------------------------------------------

    protected function redirect(string $url): ResponseInterface
    {
        return new RedirectResponse($url);
    }

    // -------------------------------------------------------------------------
    // Flash-Nachrichten
    // -------------------------------------------------------------------------

    protected function flash(string $type, string $message): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array{type: string, message: string}|null
     */
    protected function consumeFlash(): ?array
    {
        if (!isset($_SESSION['_flash'])) {
            return null;
        }
        /** @var array{type: string, message: string} $flash */
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $flash;
    }

    // -------------------------------------------------------------------------
    // Session-Helpers
    // -------------------------------------------------------------------------

    protected function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    protected function isAdmin(): bool
    {
        return !empty($_SESSION['is_admin']);
    }

    // -------------------------------------------------------------------------
    // POST-Body-Parsing (PSR-7)
    // -------------------------------------------------------------------------

    /**
     * Liest einen String-Wert sicher aus dem parsed POST-Body.
     *
     * @param array<string, mixed>|object|null $parsedBody
     */
    protected function bodyString(array|object|null $parsedBody, string $key, string $default = ''): string
    {
        if (!is_array($parsedBody)) {
            return $default;
        }
        return isset($parsedBody[$key]) ? trim((string) $parsedBody[$key]) : $default;
    }

    /**
     * Liest einen Integer-Wert sicher aus dem parsed POST-Body.
     *
     * @param array<string, mixed>|object|null $parsedBody
     */
    protected function bodyInt(array|object|null $parsedBody, string $key, int $default = 0): int
    {
        if (!is_array($parsedBody)) {
            return $default;
        }
        return isset($parsedBody[$key]) ? (int) $parsedBody[$key] : $default;
    }
}
