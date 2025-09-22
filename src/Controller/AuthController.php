<?php

namespace App\Controller;

use App\Repository\UserRepository;

class AuthController extends AbstractPageController
{
    private UserRepository $users;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->users = new UserRepository();
    }

    public function render(): void
    {
        if (($_GET['action'] ?? '') === 'logout') {
            session_destroy();
            header('Location: ?route=auth');
            exit;
        }

        if (isset($_SESSION['user_id'])) {
            header('Location: ?route=dashboard');
            exit;
        }

        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $error = $this->handleLogin();
            if ($error === null) {
                header('Location: ?route=dashboard');
                exit;
            }
        }

        $this->renderTemplate('auth/login', [
            'error' => $error,
            'config' => $this->config,
        ]);
    }

    private function handleLogin(): ?string
    {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            return 'Bitte Benutzernamen und Passwort eingeben.';
        }

        $user = $this->users->findByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return 'Ungültige Zugangsdaten.';
        }

        if (!empty($user['is_active']) && (int) $user['is_active'] === 0) {
            return 'Das Konto wurde deaktiviert.';
        }

        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = !empty($user['is_admin']);

        $this->users->updateLastLogin((int) $user['id']);

        return null;
    }
}
