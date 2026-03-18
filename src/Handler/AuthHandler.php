<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Security\PasswordHasher;
use App\Security\TotpService;
use App\Service\ThemeManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * AuthHandler — Login, Logout und MFA-Flow.
 *
 * Routen:
 *   GET  /auth/login           → Login-Formular
 *   POST /auth/login           → Zugangsdaten prüfen → ggf. MFA-Weiterleitung
 *   POST /auth/logout          → Session beenden
 *   GET  /auth/mfa/totp        → TOTP-Eingabeformular (requires mfa_pending)
 *   POST /auth/mfa/totp        → TOTP-Code verifizieren → Login abschließen
 *   GET  /auth/mfa/webauthn    → WebAuthn-Challenge-Seite (requires mfa_pending)
 *
 * MFA-Ablauf:
 *   1. Zugangsdaten korrekt aber MFA aktiv → $_SESSION['mfa_pending'] setzen
 *   2. User wird auf die passende MFA-Seite weitergeleitet
 *   3. Erfolgreiche MFA → mfa_pending löschen → user_id in Session setzen
 */
class AuthHandler extends AbstractHandler implements RequestHandlerInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(
        ThemeManager $theme,
        private readonly UserRepository $users,
        private readonly WebAuthnCredentialRepository $webAuthnCredentials,
        private readonly TotpService $totp,
        private readonly PasswordHasher $passwordHasher,
        private readonly array $config,
    ) {
        parent::__construct($theme);
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
        if (!empty($_SESSION['user_id'])) {
            return $this->redirect('/dashboard');
        }

        return $this->render('auth/login', [
            'error'  => null,
            'config' => $this->config,
        ]);
    }

    private function handleLogin(ServerRequestInterface $request): ResponseInterface
    {
        if (!empty($_SESSION['user_id'])) {
            return $this->redirect('/dashboard');
        }

        $body     = $request->getParsedBody();
        $username = $this->bodyString($body, 'username');
        $password = $this->bodyString($body, 'password');

        if ($username === '' || $password === '') {
            return $this->renderLoginError('Bitte Benutzernamen und Passwort eingeben.');
        }

        $user = $this->users->findByUsername($username);
        if ($user === null || !$this->passwordHasher->verify($password, (string) ($user['password_hash'] ?? ''))) {
            return $this->renderLoginError('Ungültige Zugangsdaten.');
        }

        if (isset($user['is_active']) && (int) $user['is_active'] === 0) {
            return $this->renderLoginError('Dieses Konto wurde deaktiviert.');
        }

        $userId = (int) $user['id'];

        // --- MFA prüfen: WebAuthn hat Vorrang vor TOTP ---
        $hasWebAuthn = $this->webAuthnCredentials->countActiveByUserId($userId) > 0;
        $hasTOTP     = !empty($user['totp_enabled']);

        if ($hasWebAuthn) {
            $_SESSION['mfa_pending']  = ['user_id' => $userId, 'type' => 'webauthn'];
            $_SESSION['mfa_username'] = $username;
            return $this->redirect('/auth/mfa/webauthn');
        }

        if ($hasTOTP) {
            $_SESSION['mfa_pending']  = ['user_id' => $userId, 'type' => 'totp'];
            $_SESSION['mfa_username'] = $username;
            return $this->redirect('/auth/mfa/totp');
        }

        // Kein MFA → direkte Anmeldung
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
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
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
        $pending = $_SESSION['mfa_pending'];
        $userId  = (int) $pending['user_id'];

        $body = $request->getParsedBody();
        $code = $this->bodyString($body, 'code');

        if ($code === '') {
            return $this->renderTotpError('Bitte Code eingeben.');
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return $this->redirect('/auth/login');
        }

        $secret = (string) ($user['totp_secret'] ?? '');
        if ($secret === '' || !$this->totp->verify($code, $secret)) {
            return $this->renderTotpError('Ungültiger Code. Bitte erneut versuchen.');
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
    // WebAuthn-MFA-Seite (Challenge-Daten kommen per AJAX)
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
    // Gemeinsame Hilfsmethoden
    // =========================================================================

    private function completeLogin(int $userId, string $username, bool $isAdmin): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        unset($_SESSION['mfa_pending'], $_SESSION['mfa_username']);

        $_SESSION['user_id']  = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['is_admin'] = $isAdmin;

        $this->users->updateLastLogin($userId);
    }

    /**
     * @param 'totp'|'webauthn' $type
     */
    private function hasMfaPending(string $type): bool
    {
        return isset($_SESSION['mfa_pending'])
            && is_array($_SESSION['mfa_pending'])
            && isset($_SESSION['mfa_pending']['type'], $_SESSION['mfa_pending']['user_id'])
            && $_SESSION['mfa_pending']['type'] === $type;
    }
}
