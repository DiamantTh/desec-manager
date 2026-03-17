<?php

namespace App\Controller;

use App\Database\DatabaseConnection;
use Doctrine\DBAL\Connection;

abstract class BaseController
{
    /** @var array<string, mixed> */
    protected array $config;
    protected Connection $connection;

    /**
     * @param array<string, mixed> $config
     */
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

    /**
     * @return array{type: string, message: string}|null
     */
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
