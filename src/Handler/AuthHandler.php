<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Security\PasswordHasher;
use App\Security\TotpService;
use App\Security\UserKeyManager;
use App\Service\ThemeManager;
use App\Session\SessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * AuthHandler — Login, Logout and MFA flow.
 *
 * Routes:
 *   GET  /auth/login           → Login form
 *   POST /auth/login           → Verify credentials → MFA redirect if needed
 *   POST /auth/logout          → End session
 *   GET  /auth/mfa/totp        → TOTP input form (requires mfa_pending)
 *   POST /auth/mfa/totp        → Verify TOTP code → Complete login
 *   GET  /auth/mfa/webauthn    → WebAuthn challenge page (requires mfa_pending)
 *
 * MFA flow:
 *   1. Credentials correct but MFA active → set $_SESSION['mfa_pending']
 *   2. User redirected to appropriate MFA page
 *   3. Successful MFA → clear mfa_pending → set user_id in session
 */
class AuthHandler extends AbstractHandler implements RequestHandlerInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(
        ThemeManager $theme,
        SessionContext $sessionContext,
        private readonly UserRepository $users,
        private readonly WebAuthnCredentialRepository $webAuthnCredentials,
        private readonly TotpService $totp,
        private readonly PasswordHasher $passwordHasher,
        private readonly UserKeyManager $userKeyManager,
        private readonly array $config,
    ) {
        parent::__construct($theme, $sessionContext);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path   = $request->getUri()->getPath();
        $method = $request->getMethod();

        return match (true) {
            $path === '/auth/login'        && $method === 'GET'  => $this->showLogin(),
            $path === '/auth/login'        && $method === 'POST' => $this->handleLogin($request),
            $path === '/auth/logout'       && $method === 'POST' => $this->handleLogout(),
            $path === '/auth/mfa/totp'     && $method === 'GET'  => $this->showTotpForm(),
            $path === '/auth/mfa/totp'     && $method === 'POST' => $this->handleTotpVerify($request),
            $path === '/auth/mfa/webauthn' && $method === 'GET'  => $this->showWebAuthnChallenge(),
            default => $this->redirect('/auth/login'),
        };
    }

    // =========================================================================
    // Login
    // =========================================================================

    private function showLogin(): ResponseInterface
    {
        if ($this->sessionContext->has('user_id')) {
            return $this->redirect('/dashboard');
        }

        return $this->render('auth/login', [
            'error'  => null,
            'config' => $this->config,
        ]);
    }

    private function handleLogin(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->sessionContext->has('user_id')) {
            return $this->redirect('/dashboard');
        }

        $body     = $request->getParsedBody();
        $username = $this->bodyString($body, 'username');
        $password = $this->bodyString($body, 'password');

        if ($username === '' || $password === '') {
            return $this->renderLoginError(__('Please enter your username and password.'));
        }

        $user = $this->users->findByUsername($username);
        if ($user === null || !$this->passwordHasher->verify($password, (string) ($user['password_hash'] ?? ''))) {
            return $this->renderLoginError(__('Invalid credentials.'));
        }

        if (isset($user['is_active']) && (int) $user['is_active'] === 0) {
            return $this->renderLoginError(__('This account has been deactivated.'));
        }

        $userId = (int) $user['id'];

        // User-Key entsperren (per-User-Verschlüsselung, Fehler führen zum App-Key-Fallback)
        $b64Salt    = (string) ($user['enc_key_salt']    ?? '');
        $b64Wrapped = (string) ($user['enc_key_wrapped'] ?? '');
        if ($b64Salt !== '' && $b64Wrapped !== '') {
            try {
                $this->userKeyManager->unlockFromLogin($password, $b64Salt, $b64Wrapped);
            } catch (\Throwable) {
                // Key-Entsperrung fehlgeschlagen — Login dennoch erlauben (Fallback auf App-Key)
            }
        }

        // --- MFA prüfen: WebAuthn hat Vorrang vor TOTP ---
        $hasWebAuthn = $this->webAuthnCredentials->countActiveByUserId($userId) > 0;
        $hasTOTP     = !empty($user['totp_enabled']);

        if ($hasWebAuthn) {
            $this->sessionContext->set('mfa_pending', ['user_id' => $userId, 'type' => 'webauthn']);
            $this->sessionContext->set('mfa_username', $username);
            return $this->redirect('/auth/mfa/webauthn');
        }

        if ($hasTOTP) {
            $this->sessionContext->set('mfa_pending', ['user_id' => $userId, 'type' => 'totp']);
            $this->sessionContext->set('mfa_username', $username);
            return $this->redirect('/auth/mfa/totp');
        }

        // No MFA → direct login
        $this->completeLogin($userId, $username, (bool) ($user['is_admin'] ?? false));
        return $this->redirect('/dashboard');
    }

    private function renderLoginError(string $error): ResponseInterface
    {
        return $this->render('auth/login', [
            'error'  => $error,
            'config' => $this->config,
        ]);
    }

    // =========================================================================
    // Logout
    // =========================================================================

    private function handleLogout(): ResponseInterface
    {
        $this->sessionContext->clear();
        return $this->redirect('/auth/login');
    }

    // =========================================================================
    // TOTP-MFA
    // =========================================================================

    private function showTotpForm(): ResponseInterface
    {
        if (!$this->hasMfaPending('totp')) {
            return $this->redirect('/auth/login');
        }

        return $this->render('auth/mfa-totp', [
            'error'  => null,
            'config' => $this->config,
        ]);
    }

    private function handleTotpVerify(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->hasMfaPending('totp')) {
            return $this->redirect('/auth/login');
        }

        /** @var array{user_id: int, type: string} $pending */
        $pending = $this->sessionContext->get('mfa_pending');
        $userId  = (int) $pending['user_id'];

        $body = $request->getParsedBody();
        $code = $this->bodyString($body, 'code');

        if ($code === '') {
            return $this->renderTotpError(__('Please enter the code.'));
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return $this->redirect('/auth/login');
        }

        $secret = (string) ($user['totp_secret'] ?? '');
        if ($secret === '' || !$this->totp->verify($code, $secret)) {
            return $this->renderTotpError(__('Invalid code. Please try again.'));
        }

        $username = (string) ($user['username'] ?? '');
        $this->completeLogin($userId, $username, (bool) ($user['is_admin'] ?? false));
        return $this->redirect('/dashboard');
    }

    private function renderTotpError(string $error): ResponseInterface
    {
        return $this->render('auth/mfa-totp', [
            'error'  => $error,
            'config' => $this->config,
        ]);
    }

    // =========================================================================
    // WebAuthn-MFA page (Challenge data comes via AJAX)
    // =========================================================================

    private function showWebAuthnChallenge(): ResponseInterface
    {
        if (!$this->hasMfaPending('webauthn')) {
            return $this->redirect('/auth/login');
        }

        return $this->render('auth/mfa-webauthn', [
            'config' => $this->config,
        ]);
    }

    // =========================================================================
    // Common helper methods
    // =========================================================================

    private function completeLogin(int $userId, string $username, bool $isAdmin): void
    {
        $this->sessionContext->regenerate();

        $this->sessionContext->unset('mfa_pending');
        $this->sessionContext->unset('mfa_username');

        $this->sessionContext->set('user_id',  $userId);
        $this->sessionContext->set('username', $username);
        $this->sessionContext->set('is_admin', $isAdmin);

        $this->userKeyManager->promoteToSession();
        $this->users->updateLastLogin($userId);
    }

    /**
     * @param 'totp'|'webauthn' $type
     */
    private function hasMfaPending(string $type): bool
    {
        $pending = $this->sessionContext->get('mfa_pending');
        return is_array($pending)
            && isset($pending['type'], $pending['user_id'])
            && $pending['type'] === $type;
    }
}
