<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\UserRepository;
use App\Security\TotpService;
use App\Service\ThemeManager;
use App\Session\SessionContext;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * TotpApiHandler — JSON API for TOTP setup and management.
 *
 * Alle Endpunkte erfordern eine authentifizierte Session (AuthMiddleware).
 *
 * Routen (definiert in config/routes.php):
 *   GET  /totp/setup    → Generate new secret and return provisioning URI
 *   POST /totp/enable   → Code verifizieren + TOTP aktivieren
 *   POST /totp/disable  → TOTP deaktivieren
 */
class TotpApiHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager $theme,
        SessionContext $sessionContext,
        private readonly TotpService $totp,
        private readonly UserRepository $users,
    ) {
        parent::__construct($theme, $sessionContext);
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
            return $this->jsonError(__('User not found.'), 404);
        }

        try {
            $secret    = $this->totp->generateSecret();
            $label     = (string) ($user['username'] ?? 'user');
            $issuer    = 'DeSEC Manager';
            $uri       = $this->totp->getProvisioningUri($secret, $label, $issuer);

            // Store the secret temporarily in session until confirmed
            $this->sessionContext->set('totp_setup_secret', $secret);

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
        if ($secret === '') {
            $secret = (string) ($this->sessionContext->get('totp_setup_secret') ?? '');
        }

        if ($code === '' || $secret === '') {
            return $this->jsonError('code und secret werden benötigt.');
        }

        if (!$this->totp->verify($code, $secret)) {
            return $this->jsonError(__('Invalid code. Please try again.'));
        }

        try {
            $this->users->enableTotp($userId, $secret);
            $this->sessionContext->unset('totp_setup_secret');
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
