<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Security\PasswordHasher;
use App\Security\UserKeyManager;
use App\Service\ThemeManager;
use App\Service\AuthorizationService;
use App\Session\SessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ProfileHandler — profile view, password change and MFA overview.
 *
 * FIDO2/TOTP management (JSON API for JS calls) is handled by
 * WebAuthnApiHandler und TotpApiHandler.
 */
class ProfileHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager $theme,
        SessionContext $sessionContext,
        AuthorizationService $authz,
        private readonly UserRepository $users,
        private readonly WebAuthnCredentialRepository $webAuthnCredentials,
        private readonly PasswordHasher $passwordHasher,
        private readonly UserKeyManager $userKeyManager,
    ) {
        parent::__construct($theme, $sessionContext, $authz);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->userId();
        $user   = $this->users->findById($userId);

        if ($user === null) {
            return $this->redirect('/auth/login');
        }

        $message     = null;
        $messageType = 'is-success';

        if ($request->getMethod() === 'POST') {
            $body   = $request->getParsedBody();
            $action = $this->bodyString($body, 'action');

            try {
                if ($action === 'change_password') {
                    $this->handleChangePassword($body, $userId, (string) ($user['password_hash'] ?? ''));
                    $message = __('Password changed successfully.');
                } elseif ($action === 'update_preferences') {
                    $this->handleUpdatePreferences($body, $userId);
                    $message = __('Preferences saved.');
                }
            } catch (\Throwable $e) {
                $message     = $e->getMessage();
                $messageType = 'is-danger';
            }

            // Reload to get fresh data
            $user = $this->users->findById($userId) ?? $user;
        }

        $webAuthnKeys = $this->webAuthnCredentials->findByUserId($userId);

        return $this->render('profile/index', [
            'user'           => $user,
            'webAuthnKeys'   => $webAuthnKeys,
            'totpEnabled'    => !empty($user['totp_enabled']),
            'message'        => $message,
            'messageType'    => $messageType,
            'availableThemes' => $this->theme->getAvailableThemes(),
        ]);
    }

    /**
     * @param array<string, mixed>|object|null $body
     */
    private function handleChangePassword(
        array|object|null $body,
        int $userId,
        string $currentHash
    ): void {
        $current  = $this->bodyString($body, 'current_password');
        $new      = $this->bodyString($body, 'new_password');
        $confirm  = $this->bodyString($body, 'new_password_confirm');

        if ($current === '' || $new === '' || $confirm === '') {
            throw new \InvalidArgumentException(__('Please fill in all fields.'));
        }
        if (!$this->passwordHasher->verify($current, $currentHash)) {
            throw new \InvalidArgumentException(__('Current password is incorrect.'));
        }
        if ($new !== $confirm) {
            throw new \InvalidArgumentException(__('New password and confirmation do not match.'));
        }
        if (strlen($new) < 12) {
            throw new \InvalidArgumentException(__('The new password must be at least 12 characters long.'));
        }

        $this->users->updatePassword($userId, $this->passwordHasher->hash($new));

        // Verschlüsselungsschlüssel mit neuem Passwort neu verpacken (kein Re-Encrypt aller API-Keys)
        if ($this->userKeyManager->hasSessionKey()) {
            $keyData = $this->userKeyManager->reWrapOnPasswordChange($new);
            $this->users->updateWrappedKey($userId, $keyData['salt'], $keyData['wrapped_key']);
        }
    }

    /**
     * @param array<string, mixed>|object|null $body
     */
    private function handleUpdatePreferences(array|object|null $body, int $userId): void
    {
        $theme  = $this->bodyString($body, 'theme');
        $locale = $this->bodyString($body, 'locale');

        // Whitelist: nur bekannte themes zulassen
        $available = $this->theme->getAvailableThemes();
        if ($theme !== '' && !in_array($theme, $available, true)) {
            throw new \InvalidArgumentException(__('Unknown theme selected.'));
        }

        // Locale: nur einfache Buchstaben/Unterstrich/Bindestrich zulassen
        if ($locale !== '' && !preg_match('/^[a-z]{2}(?:[_-][A-Z]{2})?$/', $locale)) {
            throw new \InvalidArgumentException(__('Invalid locale.'));
        }

        $this->users->updatePreferences($userId, $theme ?: 'default', $locale ?: 'en');

        // Session direkt aktualisieren (wirkt ohne Re-Login)
        if ($theme !== '')  { $this->sessionContext->set('user_theme', $theme); }
        if ($locale !== '') { $this->sessionContext->set('locale', $locale); }
    }
}
