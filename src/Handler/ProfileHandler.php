<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Security\PasswordHasher;
use App\Service\ThemeManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ProfileHandler — Profil-Ansicht, Passwortänderung und MFA-Übersicht.
 *
 * Die FIDO2/TOTP-Verwaltung (JSON-API für JS-Aufrufe) liegt in
 * WebAuthnApiHandler und TotpApiHandler.
 */
class ProfileHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager $theme,
        private readonly UserRepository $users,
        private readonly WebAuthnCredentialRepository $webAuthnCredentials,
        private readonly PasswordHasher $passwordHasher,
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
                    $message = 'Passwort wurde geändert.';
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
            throw new \InvalidArgumentException('Bitte alle Felder ausfüllen.');
        }
        if (!$this->passwordHasher->verify($current, $currentHash)) {
            throw new \InvalidArgumentException('Aktuelles Passwort ist falsch.');
        }
        if ($new !== $confirm) {
            throw new \InvalidArgumentException('Neues Passwort und Wiederholung stimmen nicht überein.');
        }
        if (strlen($new) < 12) {
            throw new \InvalidArgumentException('Das neue Passwort muss mindestens 12 Zeichen lang sein.');
        }

        $this->users->updatePassword($userId, $this->passwordHasher->hash($new));
    }
}
