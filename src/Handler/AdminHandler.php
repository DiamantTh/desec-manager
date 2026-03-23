<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Security\UserKeyManager;
use App\Service\ThemeManager;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager $theme,
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwordHasher,
        private readonly UserKeyManager $userKeyManager,
    ) {
        parent::__construct($theme);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return new HtmlResponse('<div class="notification is-danger">Zugriff verweigert.</div>', 403);
        }

        $message     = null;
        $messageType = 'is-success';

        if ($request->getMethod() === 'POST') {
            $body   = $request->getParsedBody();
            $action = $this->bodyString($body, 'action');

            try {
                if ($action === 'add') {
                    $this->handleAdd($body);
                    $message = __('Administrator created successfully.');
                } elseif ($action === 'deactivate') {
                    $id = $this->bodyInt($body, 'id');
                    if ($id > 0) {
                        $this->users->setActive($id, false);
                        $message = __('User has been deactivated.');
                    }
                } elseif ($action === 'delete') {
                    $id = $this->bodyInt($body, 'id');
                    if ($id > 0 && $id !== $this->userId()) {
                        $this->users->delete($id);
                        $message = __('User has been deleted.');
                    }
                }
            } catch (\Throwable $e) {
                $message     = $e->getMessage();
                $messageType = 'is-danger';
            }
        }

        return $this->render('admin/index', [
            'admins'      => $this->users->findAdmins(),
            'message'     => $message,
            'messageType' => $messageType,
        ]);
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

        $userId = $this->users->create([
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $this->passwordHasher->hash($password),
            'is_admin'      => true,
            'is_active'     => true,
        ]);

        $keyData = $this->userKeyManager->initForNewUser($password);
        $this->users->updateWrappedKey($userId, $keyData['salt'], $keyData['wrapped_key']);
    }
}
