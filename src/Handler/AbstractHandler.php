<?php

declare(strict_types=1);

namespace App\Handler;

use App\Session\SessionContext;
use App\Service\AuthorizationService;
use App\Service\ThemeManager;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Csrf\CsrfGuardInterface;
use Mezzio\Csrf\CsrfMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * AbstractHandler — gemeinsame Basis für alle PSR-15-Handler im deSEC Manager.
 *
 * Provides:
 *   - render()        → PHP-Template via ThemeManager laden → HtmlResponse
 *   - redirect()      → RedirectResponse
 *   - flash()         → Flash-Nachricht in Session schreiben
 *   - consumeFlash()  → Flash-Nachricht lesen + aus Session entfernen
 *   - userId()        → Aktuell eingeloggter Benutzer (int, 0 wenn nicht eingeloggt)
 *   - isAdmin()       → Admin-Flag aus der Session
 */
abstract class AbstractHandler
{
    public function __construct(
        protected readonly ThemeManager $theme,
        protected readonly SessionContext $sessionContext,
        protected readonly AuthorizationService $authz,
    ) {
        // Per-User-Theme aus Session: überschreibt globale Konfiguration.
        $userTheme = (string) $this->sessionContext->get('user_theme', '');
        if ($userTheme !== '') {
            $this->theme->setThemeName($userTheme);
        }
    }

    // -------------------------------------------------------------------------
    // Template rendering
    // -------------------------------------------------------------------------

    /**
     * Renders a PHP template via ThemeManager (3-level fallback resolution).
     * If a layout template exists, wraps the fragment in the full page layout.
     *
     * @param array<string, mixed>       $data     Template variables
     * @param ServerRequestInterface|null $request  Current request (used for layout + CSRF)
     */
    protected function render(
        string $template,
        array $data = [],
        ?ServerRequestInterface $request = null
    ): ResponseInterface {
        $path = $this->theme->resolveTemplate($template);
        if (!file_exists($path)) {
            return new HtmlResponse(
                '<h1>500 – ' . htmlspecialchars(__('Template not found')) . '</h1><p>' . htmlspecialchars($template) . '</p>',
                500
            );
        }

        $data['theme'] = $this->theme;
        $content       = $this->renderToString($path, $data);

        // Wrap in layout if one exists
        $layoutPath = $this->theme->resolveTemplate('layout');
        if (file_exists($layoutPath) && $request !== null) {
            // Generate a CSRF token for the layout (logout form) if not already provided
            if (!isset($data['csrfToken'])) {
                try {
                    $data['csrfToken'] = $this->generateCsrfToken($request);
                } catch (\Throwable) {
                    $data['csrfToken'] = '';
                }
            }

            $layoutData = [
                'content'       => $content,
                'theme'         => $this->theme,
                'sessionContext' => $this->sessionContext,
                'currentPath'   => $request->getUri()->getPath(),
                'csrfToken'     => (string) $data['csrfToken'],
            ];

            return new HtmlResponse($this->renderToString($layoutPath, $layoutData));
        }

        return new HtmlResponse($content);
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
    // CSRF (mezzio/mezzio-csrf)
    // -------------------------------------------------------------------------

    /**
     * Gibt den CSRF-Guard für diesen Request zurück.
     * Wirft eine RuntimeException wenn CsrfMiddleware nicht in der Pipeline ist.
     */
    protected function getCsrfGuard(ServerRequestInterface $request): CsrfGuardInterface
    {
        $guard = $request->getAttribute(CsrfMiddleware::GUARD_ATTRIBUTE);
        if (!$guard instanceof CsrfGuardInterface) {
            throw new \RuntimeException(
                'CsrfGuardInterface nicht gefunden. Ist CsrfMiddleware in der Pipeline registriert?'
            );
        }
        return $guard;
    }

    /**
     * Generiert ein CSRF-Token und gibt es zurück (für Formular-Rendering).
     */
    protected function generateCsrfToken(ServerRequestInterface $request): string
    {
        return $this->getCsrfGuard($request)->generateToken();
    }

    /**
     * Validiert das CSRF-Token aus dem POST-Body.
     * Gibt eine 403-HtmlResponse zurück bei Fehler, null bei Erfolg.
     */
    protected function validateCsrf(ServerRequestInterface $request): ?ResponseInterface
    {
        $guard  = $this->getCsrfGuard($request);
        $body   = $request->getParsedBody();
        $token  = is_array($body) ? (string)($body['csrf'] ?? '') : '';

        if (!$guard->validateToken($token)) {
            return new HtmlResponse(
                '<h1>403 – ' . htmlspecialchars(__('CSRF validation failed.')) . '</h1>',
                403
            );
        }

        return null;
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
        $this->sessionContext->set('_flash', ['type' => $type, 'message' => $message]);
    }

    /**
     * @return array{type: string, message: string}|null
     */
    protected function consumeFlash(): ?array
    {
        /** @var array{type: string, message: string}|null $flash */
        $flash = $this->sessionContext->get('_flash');
        if ($flash === null) {
            return null;
        }
        $this->sessionContext->unset('_flash');
        return $flash;
    }

    // -------------------------------------------------------------------------
    // Session-Helpers
    // -------------------------------------------------------------------------

    protected function userId(): int
    {
        return (int) $this->sessionContext->get('user_id', 0);
    }

    protected function isAdmin(): bool
    {
        return (bool) $this->sessionContext->get('is_admin', false);
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
