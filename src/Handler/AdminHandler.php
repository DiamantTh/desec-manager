<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\UserRepository;
use App\Repository\SessionRepository;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;
use App\Security\UserKeyManager;
use App\Service\ThemeManager;
use App\Service\AuthorizationService;
use App\Session\SessionContext;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager $theme,
        SessionContext $sessionContext,
        AuthorizationService $authz,
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwordHasher,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly UserKeyManager $userKeyManager,
        private readonly SessionRepository $sessionRepository,
    ) {
        parent::__construct($theme, $sessionContext, $authz);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return new HtmlResponse('<div class="notification is-danger">Zugriff verweigert.</div>', 403);
        }

        $message     = null;
        $messageType = 'is-success';

        if ($request->getMethod() === 'POST') {
            if ($csrfError = $this->validateCsrf($request)) {
                return $csrfError;
            }

            $body   = $request->getParsedBody();
            $action = $this->bodyString($body, 'action');

            try {
                if ($action === 'add') {
                    $this->handleAdd($body);
                    $message = __('User created successfully.');
                } elseif ($action === 'deactivate') {
                    $id = $this->bodyInt($body, 'id');
                    if ($id > 0) {
                        $this->users->setActive($id, false);
                        $message = __('User has been deactivated.');
                    }
                } elseif ($action === 'activate') {
                    $id = $this->bodyInt($body, 'id');
                    if ($id > 0) {
                        $this->users->setActive($id, true);
                        $message = __('User has been activated.');
                    }
                } elseif ($action === 'promote') {
                    $id = $this->bodyInt($body, 'id');
                    if ($id > 0) {
                        $this->users->setAdmin($id, true);
                        $message = __('User has been promoted to administrator.');
                    }
                } elseif ($action === 'demote') {
                    $id = $this->bodyInt($body, 'id');
                    if ($id > 0 && $id !== $this->userId()) {
                        $this->users->setAdmin($id, false);
                        $message = __('Administrator privileges have been revoked.');
                    }
                } elseif ($action === 'delete') {
                    $id = $this->bodyInt($body, 'id');
                    if ($id > 0 && $id !== $this->userId()) {
                        $this->users->delete($id);
                        $message = __('User has been deleted.');
                    }
                } elseif ($action === 'invalidate_session') {
                    $id = $this->bodyInt($body, 'id');
                    if ($id > 0) {
                        $this->sessionRepository->invalidate($id);
                        $message = __('Session has been invalidated.');
                    }
                } elseif ($action === 'reset_password') {
                    $this->handleResetPassword($body);
                    $message = __('Password has been reset successfully.');
                }
            } catch (\Throwable $e) {
                $message     = $e->getMessage();
                $messageType = 'is-danger';
            }
        }

        return $this->render('admin/index', [
            'users'       => $this->users->findAll(),
            'sessions'    => $this->sessionRepository->findAll(),
            'currentId'   => $this->userId(),
            'csrfToken'   => $this->generateCsrfToken($request),
            'message'     => $message,
            'messageType' => $messageType,
            'minLength'   => $this->passwordPolicy->getMinLength(),
        ], $request);
    }

    /**
     * @param array<string, mixed>|object|null $body
     */
    private function handleAdd(array|object|null $body): void
    {
        $username = $this->bodyString($body, 'username');
        $email    = $this->bodyString($body, 'email');
        $password = $this->bodyString($body, 'password');

        if ($username === '' || $email === '' || $password === '') {
            throw new \InvalidArgumentException(__('Please fill in all fields.'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(__('Invalid email address.'));
        }
        if (!$this->users->isUsernameAvailable($username)) {
            throw new \RuntimeException(__('Username already taken.'));
        }
        if (!$this->users->isEmailAvailable($email)) {
            throw new \RuntimeException('E-Mail-Adresse wird bereits verwendet.');
        }

        $this->passwordPolicy->assertValid($password);

        $userId = $this->users->create([
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $this->passwordHasher->hash($password),
            'is_admin'      => is_array($body) && !empty($body['is_admin']),
            'is_active'     => true,
        ]);

        $keyData = $this->userKeyManager->initForNewUser($password);
        $this->users->updateWrappedKey($userId, $keyData['salt'], $keyData['wrapped_key']);
    }

    /**
     * Setzt das Passwort eines Benutzers als Admin zurück.
     * Kein Altes Passwort nötig. Der Encryption-Key wird neu initialisiert —
     * vorhandene verschlüsselte API-Keys des Benutzers werden dabei ungültig.
     *
     * @param array<string, mixed>|object|null $body
     */
    private function handleResetPassword(array|object|null $body): void
    {
        $targetId = $this->bodyInt($body, 'target_id');
        $newPw    = $this->bodyString($body, 'new_password');
        $confirm  = $this->bodyString($body, 'new_password_confirm');

        if ($targetId <= 0) {
            throw new \InvalidArgumentException(__('Please select a user.'));
        }
        if ($newPw === '' || $confirm === '') {
            throw new \InvalidArgumentException(__('Please fill in all fields.'));
        }
        if ($newPw !== $confirm) {
            throw new \InvalidArgumentException(__('New password and confirmation do not match.'));
        }

        $this->passwordPolicy->assertValid($newPw);

        $user = $this->users->findById($targetId);
        if ($user === null) {
            throw new \RuntimeException(__('User not found.'));
        }

        $this->users->updatePassword($targetId, $this->passwordHasher->hash($newPw));

        // Encryption-Key mit neuem Passwort neu initialisieren
        $keyData = $this->userKeyManager->initForNewUser($newPw);
        $this->users->updateWrappedKey($targetId, $keyData['salt'], $keyData['wrapped_key']);

        // Alle aktiven Sessions des Benutzers invalidieren (Sicherheit: erzwingt Re-Login)
        $this->sessionRepository->invalidateByUserId($targetId);
    }
}
