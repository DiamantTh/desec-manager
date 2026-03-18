<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\UserRepository;
use App\Security\TotpService;
use App\Service\ThemeManager;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * TotpApiHandler — JSON-API für TOTP-Setup und -Verwaltung.
 *
 * Alle Endpunkte erfordern eine authentifizierte Session (AuthMiddleware).
 *
 * Routen (definiert in config/routes.php):
 *   GET  /totp/setup    → Neues Secret generieren + provisioning URI zurückgeben
 *   POST /totp/enable   → Code verifizieren + TOTP aktivieren
 *   POST /totp/disable  → TOTP deaktivieren
 */
class TotpApiHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager $theme,
        private readonly TotpService $totp,
        private readonly UserRepository $users,
    ) {
        parent::__construct($theme);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path   = $request->getUri()->getPath();
        $method = $request->getMethod();

        return match (true) {
            $path === '/totp/setup'   && $method === 'GET'  => $this->setup(),
            $path === '/totp/enable'  && $method === 'POST' => $this->enable($request),
            $path === '/totp/disable' && $method === 'POST' => $this->disable(),
            default => new JsonResponse(['error' => 'Not found'], 404),
        };
    }

    // =========================================================================
    // Setup — neues Secret generieren
    // =========================================================================

    private function setup(): ResponseInterface
    {
        $userId = $this->userId();
        $user   = $this->users->findById($userId);
        if ($user === null) {
            return $this->jsonError('Benutzer nicht gefunden.', 404);
        }

        try {
            $secret    = $this->totp->generateSecret();
            $label     = (string) ($user['username'] ?? 'user');
            $issuer    = 'DeSEC Manager';
            $uri       = $this->totp->getProvisioningUri($secret, $label, $issuer);

            // Secret temporär in der Session speichern bis es bestätigt wurde
            $_SESSION['totp_setup_secret'] = $secret;

            return new JsonResponse([
                'secret'           => $secret,
                'provisioning_uri' => $uri,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Enable — Code verifizieren + TOTP dauerhaft aktivieren
    // =========================================================================

    private function enable(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->userId();
        $body   = $request->getParsedBody();
        $code   = $this->bodyString($body, 'code');
        $secret = $this->bodyString($body, 'secret');

        // Secret aus Session als Fallback (falls kein secret im Body)
        if ($secret === '' && isset($_SESSION['totp_setup_secret'])) {
            $secret = (string) $_SESSION['totp_setup_secret'];
        }

        if ($code === '' || $secret === '') {
            return $this->jsonError('code und secret werden benötigt.');
        }

        if (!$this->totp->verify($code, $secret)) {
            return $this->jsonError('Ungültiger Code. Bitte erneut versuchen.');
        }

        try {
            $this->users->enableTotp($userId, $secret);
            unset($_SESSION['totp_setup_secret']);
            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Disable — TOTP deaktivieren
    // =========================================================================

    private function disable(): ResponseInterface
    {
        $userId = $this->userId();

        try {
            $this->users->disableTotp($userId);
            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Hilfsmethoden
    // =========================================================================

    private function jsonError(string $message, int $status = 400): ResponseInterface
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
