<?php

namespace App\Controller;

use App\Repository\UserRepository;

class ProfileController extends AbstractPageController
{
    private UserRepository $users;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->users = new UserRepository();
    }

    public function render(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $user = $this->users->findById($userId);

        $this->renderTemplate('profile/index', [
            'user' => $user,
        ]);
    }
}
