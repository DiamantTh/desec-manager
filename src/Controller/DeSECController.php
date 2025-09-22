<?php

namespace App\Controller;

use App\Service\DeSECProxyService;

class DeSECController
{
    private DeSECProxyService $desecProxy;

    public function __construct()
    {
        $this->desecProxy = new DeSECProxyService();
    }

    public function execute(array $payload): array
    {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'error' => 'Nicht authentifiziert'];
        }

        $userId = (int) $_SESSION['user_id'];
        $domain = $payload['domain'] ?? '';
        $operation = $payload['operation'] ?? '';
        $params = $payload['params'] ?? [];

        if ($domain === '' || $operation === '') {
            return ['success' => false, 'error' => 'Ungültige Anfrage'];
        }

        try {
            $result = $this->desecProxy->executeRequest($userId, $domain, $operation, $params);
            return ['success' => true, 'data' => $result];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function listDomains(): array
    {
        if (!isset($_SESSION['user_id'])) {
            return ['success' => false, 'error' => 'Nicht authentifiziert'];
        }

        try {
            $domains = $this->desecProxy->getDomainOverview((int) $_SESSION['user_id']);
            return ['success' => true, 'domains' => $domains];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
