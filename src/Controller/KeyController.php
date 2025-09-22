<?php

namespace App\Controller;

use App\Repository\ApiKeyRepository;
use App\Security\EncryptionService;

class KeyController extends AbstractPageController
{
    private ApiKeyRepository $apiKeys;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->apiKeys = new ApiKeyRepository();
    }

    public function render(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $message = null;
        $messageType = 'is-success';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'create') {
                    $this->handleCreate($userId);
                    $message = 'API Key wurde gespeichert.';
                } elseif ($action === 'deactivate') {
                    $this->handleDeactivate($userId);
                    $message = 'API Key wurde deaktiviert.';
                }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $messageType = 'is-danger';
            }
        }

        $keys = $this->apiKeys->findByUserId($userId);

        $this->renderTemplate('keys/index', [
            'apiKeys' => $keys,
            'message' => $message,
            'messageType' => $messageType,
        ]);
    }

    private function handleCreate(int $userId): void
    {
        $name = trim($_POST['name'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');

        if ($name === '' || $apiKey === '') {
            throw new \InvalidArgumentException('Name und API-Key werden benötigt.');
        }

        $this->apiKeys->create([
            'user_id' => $userId,
            'name' => $name,
            'api_key' => $apiKey,
        ]);
    }

    private function handleDeactivate(int $userId): void
    {
        $keyId = (int) ($_POST['key_id'] ?? 0);
        if ($keyId === 0) {
            throw new \InvalidArgumentException('API-Key wurde nicht gefunden.');
        }

        $this->apiKeys->deactivate($keyId, $userId);
    }
}
