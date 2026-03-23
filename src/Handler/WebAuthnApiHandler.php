<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Security\UserKeyManager;
use App\Security\WebAuthnService;
use App\Service\ThemeManager;
use App\Session\SessionContext;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * WebAuthnApiHandler — JSON API for FIDO2/WebAuthn operations.
 *
 * All endpoints return JSON (no HTML responses).
 * Authentifizierung:
 *   - Auth-Endpunkte (auth-options, verify-auth): mfa_pending oder user_id in Session
 *   - Registrierungs-/Verwaltungsendpunkte: user_id in Session (AuthMiddleware)
 *
 * Routen (definiert in config/routes.php):
 *   GET  /webauthn/register-options    → generateRegistrationOptions()
 *   POST /webauthn/verify-registration → verifyRegistration()
 *   GET  /webauthn/auth-options        → generateAuthOptions()
 *   POST /webauthn/verify-auth         → verifyAuthentication()
 *   POST /webauthn/rename              → renameKey()
 *   POST /webauthn/delete              → deleteKey()
 */
class WebAuthnApiHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager $theme,
        SessionContext $sessionContext,
        private readonly WebAuthnService $webAuthn,
        private readonly WebAuthnCredentialRepository $credentials,
        private readonly UserRepository $users,
        private readonly UserKeyManager $userKeyManager,
    ) {
        parent::__construct($theme, $sessionContext);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path   = $request->getUri()->getPath();
        $method = $request->getMethod();

        return match (true) {
            $path === '/webauthn/register-options'    && $method === 'GET'  => $this->generateRegistrationOptions(),
            $path === '/webauthn/verify-registration'  && $method === 'POST' => $this->verifyRegistration($request),
            $path === '/webauthn/auth-options'         && $method === 'GET'  => $this->generateAuthOptions(),
            $path === '/webauthn/verify-authentication' && $method === 'POST' => $this->verifyAuthentication($request),
            $path === '/webauthn/rename'              && $method === 'POST' => $this->renameKey($request),
            $path === '/webauthn/delete'              && $method === 'POST' => $this->deleteKey($request),
            default => new JsonResponse(['error' => 'Not found'], 404),
        };
    }

    // =========================================================================
    // Registrierung (authenticated user only)
    // =========================================================================

    private function generateRegistrationOptions(): ResponseInterface
    {
        $userId = $this->userId();
        if ($userId === 0) {
            return $this->jsonError(__('Not authenticated.'), 401);
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return $this->jsonError(__('User not found.'), 404);
        }

        try {
            $username   = (string) ($user['username'] ?? '');
            $userHandle = base64_encode((string) $userId);
            $existing   = $this->credentials->findByUserId($userId);

            $options = $this->webAuthn->generateRegistrationOptions($username, $userHandle, $existing);
            return new JsonResponse($options);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    private function verifyRegistration(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->userId();
        if ($userId === 0) {
            return $this->jsonError(__('Not authenticated.'), 401);
        }

        $body     = $request->getParsedBody();
        $keyName  = $this->bodyString($body, 'keyName', 'Security Key');
        $credJson = $this->bodyString($body, 'credential');

        if ($credJson === '') {
            return $this->jsonError(__('No authenticator response.'));
        }

        try {
            $credential = $this->webAuthn->verifyRegistration($keyName, $credJson);
            $this->credentials->insert($credential, $userId);
            return new JsonResponse(['success' => true, 'name' => $credential->getName()]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Authentifizierung (mfa_pending oder eingeloggt)
    // =========================================================================

    private function generateAuthOptions(): ResponseInterface
    {
        $userId = $this->resolveUserId();
        if ($userId === 0) {
            return $this->jsonError(__('No pending login process.'), 401);
        }

        try {
            $existing = $this->credentials->findByUserId($userId);
            $options  = $this->webAuthn->generateAuthenticationOptions($existing);
            return new JsonResponse($options);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    private function verifyAuthentication(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->resolveUserId();
        if ($userId === 0) {
            return $this->jsonError(__('No pending login process.'), 401);
        }

        $body     = $request->getParsedBody();
        $credJson = $this->bodyString($body, 'credential');

        if ($credJson === '') {
            return $this->jsonError(__('No authenticator response.'));
        }

        try {
            // Extract credential ID from the browser response (before full validation)
            $decoded = json_decode($credJson, true);
            if (!is_array($decoded) || !isset($decoded['id'])) {
                return $this->jsonError(__('Invalid credential JSON.'));
            }
            $credentialId = (string) $decoded['id'];

            $storedCred = $this->credentials->findByCredentialId($credentialId);
            if ($storedCred === null) {
                return $this->jsonError(__('Unknown credential.'));
            }

            $userHandle = base64_encode((string) $userId);
            $source     = $this->webAuthn->verifyAuthentication($credJson, $storedCred, $userHandle);

            // Sign-Counter aktualisieren
            $this->credentials->updateSignCount($credentialId, $source->counter);

            // If MFA is pending → complete the login
            if ($this->sessionContext->has('mfa_pending')) {
                $user = $this->users->findById($userId);
                if ($user !== null) {
                    $this->sessionContext->regenerate();
                    $this->sessionContext->unset('mfa_pending');
                    $this->sessionContext->unset('mfa_username');
                    $this->sessionContext->set('user_id',  $userId);
                    $this->sessionContext->set('username', (string) ($user['username'] ?? ''));
                    $this->sessionContext->set('is_admin', (bool)  ($user['is_admin']  ?? false));
                    $this->userKeyManager->promoteToSession();
                    $this->users->updateLastLogin($userId);
                }
            }

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Key-Verwaltung (authenticated user only)
    // =========================================================================

    private function renameKey(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->userId();
        if ($userId === 0) {
            return $this->jsonError(__('Not authenticated.'), 401);
        }

        $body         = $request->getParsedBody();
        $credentialId = $this->bodyString($body, 'credential_id');
        $name         = $this->bodyString($body, 'name');

        if ($credentialId === '' || $name === '') {
            return $this->jsonError('credential_id und name werden benötigt.');
        }

        $this->credentials->rename($credentialId, $userId, $name);
        return new JsonResponse(['success' => true]);
    }

    private function deleteKey(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->userId();
        if ($userId === 0) {
            return $this->jsonError(__('Not authenticated.'), 401);
        }

        $body         = $request->getParsedBody();
        $credentialId = $this->bodyString($body, 'credential_id');

        if ($credentialId === '') {
            return $this->jsonError('credential_id wird benötigt.');
        }

        $this->credentials->deactivate($credentialId, $userId);
        return new JsonResponse(['success' => true]);
    }

    // =========================================================================
    // Hilfsmethoden
    // =========================================================================

    /**
     * Liefert die User-ID aus der normalen Session oder aus mfa_pending.
     */
    private function resolveUserId(): int
    {
        $userId = (int) $this->sessionContext->get('user_id', 0);
        if ($userId !== 0) {
            return $userId;
        }
        /** @var array{user_id?: int}|null $pending */
        $pending = $this->sessionContext->get('mfa_pending');
        if (is_array($pending) && isset($pending['user_id'])) {
            return (int) $pending['user_id'];
        }
        return 0;
    }

    private function jsonError(string $message, int $status = 400): ResponseInterface
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
