<?php

namespace App\Controller;

use App\Database\DatabaseConnection;
use Doctrine\DBAL\Connection;

abstract class BaseController
{
    protected array $config;
    protected Connection $connection;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connection = DatabaseConnection::getConnection();
    }

    protected function setFlash(string $type, string $message): void
    {
        $_SESSION['_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    protected function consumeFlash(): ?array
    {
        if (!isset($_SESSION['_flash'])) {
            return null;
        }

        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $flash;
    }
}
