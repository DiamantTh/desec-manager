<?php

namespace App\Controller;

use App\Repository\UserRepository;

class AdminController extends AbstractPageController
{
    private UserRepository $users;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->users = new UserRepository();
    }

    public function render(): void
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            echo '<div class="notification is-danger">Zugriff verweigert.</div>';
            return;
        }

        $message = null;
        $messageType = 'is-success';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'add') {
                    $this->handleAdd();
                    $message = 'Administrator wurde angelegt.';
                }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $messageType = 'is-danger';
            }
        }

        $admins = $this->users->findAdmins();

        $this->renderTemplate('admin/index', [
            'admins' => $admins,
            'message' => $message,
            'messageType' => $messageType,
        ]);
    }

    private function handleAdd(): void
    {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === '' || $email === '' || $password === '') {
            throw new \InvalidArgumentException('Bitte alle Felder ausfüllen.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Ungültige E-Mail-Adresse.');
        }

        if (!$this->users->isUsernameAvailable($username)) {
            throw new \RuntimeException('Benutzername bereits vergeben.');
        }

        if (!$this->users->isEmailAvailable($email)) {
            throw new \RuntimeException('E-Mail-Adresse wird bereits verwendet.');
        }

        $hashAlgo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        $this->users->create([
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, $hashAlgo),
            'is_admin' => true,
            'is_active' => true,
        ]);
    }
}
