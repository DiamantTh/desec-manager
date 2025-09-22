<?php

namespace App\Controller;

use App\Repository\ApiKeyRepository;
use App\Repository\DomainRepository;
use App\Service\DNSService;
use RuntimeException;

class RecordController extends AbstractPageController
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
        $domains = $this->domains->findByUserId($userId);
        $apiKeys = $this->apiKeys->findByUserId($userId);

        $selectedDomain = $_GET['domain'] ?? ($domains[0]['domain_name'] ?? '');
        $selectedKeyId = isset($_GET['api_key']) ? (int) $_GET['api_key'] : ($apiKeys[0]['id'] ?? 0);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handlePost($userId, $selectedDomain, $selectedKeyId);
            return;
        }

        $flash = $this->consumeFlash();
        $message = $flash['message'] ?? null;
        $messageType = $flash['type'] ?? 'is-success';

        $rrsets = [];
        if ($selectedDomain && $selectedKeyId) {
            try {
                $rrsets = $this->dnsService->listRRSets($userId, $selectedKeyId, $selectedDomain);
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $messageType = 'is-danger';
            }
        }

        $this->renderTemplate('records/index', [
            'domains' => $domains,
            'apiKeys' => $apiKeys,
            'selectedDomain' => $selectedDomain,
            'selectedKeyId' => $selectedKeyId,
            'rrsets' => $rrsets,
            'message' => $message,
            'messageType' => $messageType,
        ]);
    }

    private function handlePost(int $userId, string $currentDomain, int $currentKeyId): void
    {
        $action = $_POST['action'] ?? '';
        $domain = trim($_POST['domain'] ?? $currentDomain);
        $apiKeyId = (int) ($_POST['api_key_id'] ?? $currentKeyId);

        if ($domain === '' || $apiKeyId === 0) {
            $this->setFlash('is-danger', 'Bitte Domain und API-Key auswählen.');
            $this->redirect($domain, $apiKeyId);
        }

        try {
            switch ($action) {
                case 'create':
                case 'update':
                    $this->handleUpsert($userId, $apiKeyId, $domain, $action);
                    $verb = $action === 'create' ? 'angelegt' : 'aktualisiert';
                    $this->setFlash('is-success', "RRset wurde {$verb}.");
                    break;
                case 'delete':
                    $this->handleDelete($userId, $apiKeyId, $domain);
                    $this->setFlash('is-success', 'RRset wurde gelöscht.');
                    break;
                default:
                    throw new RuntimeException('Unbekannte Aktion.');
            }
        } catch (\Throwable $e) {
            $this->setFlash('is-danger', $e->getMessage());
        }

        $this->redirect($domain, $apiKeyId);
    }

    private function handleUpsert(int $userId, int $apiKeyId, string $domain, string $action): void
    {
        $type = strtoupper(trim($_POST['type'] ?? ''));
        $subName = trim($_POST['subname'] ?? '');
        if ($subName === '@') {
            $subName = '';
        }
        $ttl = (int) ($_POST['ttl'] ?? 3600);
        $recordsRaw = $_POST['records'] ?? '';

        $allowedTypes = ['A', 'AAAA', 'ALIAS', 'CNAME', 'TXT', 'MX', 'NS', 'SRV', 'CAA', 'TLSA'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new RuntimeException('Record-Typ wird nicht unterstützt.');
        }

        if ($ttl < 30 || $ttl > 86400) {
            throw new RuntimeException('TTL muss zwischen 30 und 86400 liegen.');
        }

        $records = $this->parseRecords($recordsRaw);
        if (empty($records)) {
            throw new RuntimeException('Es muss mindestens ein Record angegeben werden.');
        }

        if ($type === 'MX') {
            foreach ($records as $record) {
                if (!preg_match('/^\d+\s+\S+$/', $record)) {
                    throw new RuntimeException('MX-Records benötigen Priorität und Host (z.B. "10 mail.example.com").');
                }
            }
        }

        $this->dnsService->upsertRRSet(
            $userId,
            $apiKeyId,
            $domain,
            $subName,
            $type,
            $records,
            $ttl
        );
    }

    private function handleDelete(int $userId, int $apiKeyId, string $domain): void
    {
        $type = strtoupper(trim($_POST['type'] ?? ''));
        $subName = trim($_POST['subname'] ?? '');
        if ($subName === '@') {
            $subName = '';
        }

        if ($type === '') {
            throw new RuntimeException('Record-Typ fehlt.');
        }

        $this->dnsService->deleteRRSet($userId, $apiKeyId, $domain, $subName, $type);
    }

    private function parseRecords(string $recordsRaw): array
    {
        $lines = preg_split('/\r?\n/', $recordsRaw);
        $records = [];
        foreach ($lines as $line) {
            $value = trim($line);
            if ($value !== '') {
                $records[] = $value;
            }
        }
        return $records;
    }

    private function redirect(string $domain, int $apiKeyId): void
    {
        $query = http_build_query(array_filter([
            'route' => 'records',
            'domain' => $domain ?: null,
            'api_key' => $apiKeyId ?: null,
        ]));
        header('Location: ?' . $query);
        exit;
    }
}
