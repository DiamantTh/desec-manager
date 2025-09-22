<?php

namespace App\Controller;

use App\Repository\DomainRepository;
use App\Repository\ApiKeyRepository;
use App\Service\DNSService;

class DomainController extends AbstractPageController
{
    private DomainRepository $domains;
    private ApiKeyRepository $apiKeys;
    private DNSService $dnsService;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->domains = new DomainRepository();
        $this->apiKeys = new ApiKeyRepository();
        $this->dnsService = new DNSService();
    }

    public function render(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $flash = $this->consumeFlash();
        $message = $flash['message'] ?? null;
        $messageType = $flash['type'] ?? 'is-success';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'add') {
                    $this->handleAdd($userId);
                    $this->setFlash('is-success', 'Domain wurde hinzugefügt.');
                } elseif ($action === 'delete') {
                    $this->handleDelete($userId);
                    $this->setFlash('is-success', 'Domain wurde entfernt.');
                } elseif ($action === 'sync') {
                    $result = $this->handleSync($userId);
                    $added = count($result['added']);
                    $removed = count($result['removed']);
                    $this->setFlash('is-success', "Synchronisation abgeschlossen ({$added} hinzugefügt, {$removed} entfernt).");
                }
                $this->redirect();
            } catch (\Throwable $e) {
                $this->setFlash('is-danger', $e->getMessage());
                $this->redirect();
            }
        }

        $domains = $this->domains->findByUserId($userId);
        $apiKeys = $this->apiKeys->findByUserId($userId);

        $this->renderTemplate('domains/index', [
            'domains' => $domains,
            'apiKeys' => $apiKeys,
            'message' => $message,
            'messageType' => $messageType,
        ]);
    }

    private function handleAdd(int $userId): void
    {
        $domain = strtolower(trim($_POST['domain'] ?? ''));
        $apiKeyId = (int) ($_POST['api_key_id'] ?? 0);

        if ($domain === '' || $apiKeyId === 0) {
            throw new \InvalidArgumentException('Bitte Domain und API-Key auswählen.');
        }

        if (!preg_match('/^[a-z0-9.-]+$/', $domain)) {
            throw new \InvalidArgumentException('Ungültiger Domainname.');
        }

        $this->dnsService->createDomain($userId, $apiKeyId, $domain);
    }

    private function handleDelete(int $userId): void
    {
        $domain = trim($_POST['domain'] ?? '');
        $apiKeyId = (int) ($_POST['api_key_id'] ?? 0);

        if ($domain === '' || $apiKeyId === 0) {
            throw new \InvalidArgumentException('Domain und API-Key werden benötigt.');
        }

        $this->dnsService->deleteDomain($userId, $apiKeyId, $domain);
    }

    private function handleSync(int $userId): array
    {
        $apiKeyId = (int) ($_POST['api_key_id'] ?? 0);
        if ($apiKeyId === 0) {
            throw new \InvalidArgumentException('Bitte einen API-Key auswählen.');
        }

        return $this->dnsService->syncDomains($userId, $apiKeyId);
    }

    private function redirect(): void
    {
        header('Location: ?route=domains');
        exit;
    }
}
