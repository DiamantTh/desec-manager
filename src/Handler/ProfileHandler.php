<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Security\PasswordHasher;
use App\Security\UserKeyManager;
use App\Service\ThemeManager;
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
        private readonly UserRepository $users,
        private readonly WebAuthnCredentialRepository $webAuthnCredentials,
        private readonly PasswordHasher $passwordHasher,
        private readonly UserKeyManager $userKeyManager,
    ) {
        parent::__construct($theme);
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
}
