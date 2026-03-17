<?php

namespace App\Service;

use App\Repository\DomainRepository;
use RuntimeException;

class DeSECProxyService
{
    private DNSService $dnsService;
    private DomainRepository $domains;

    public function __construct()
    {
        $this->dnsService = new DNSService();
        $this->domains = new DomainRepository();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int|string, mixed>
     */
    public function executeRequest(int $userId, string $domainName, string $operation, array $params = []): array
    {
        $apiKeyId = (int) ($params['api_key_id'] ?? 0);
        if ($apiKeyId === 0) {
            throw new RuntimeException('api_key_id muss angegeben werden.');
        }

        return match ($operation) {
            'getDomain' => $this->dnsService->getDomainDetails($userId, $apiKeyId, $domainName),
            'getRRSets' => $this->dnsService->listRRSets($userId, $apiKeyId, $domainName),
            'createRRSet', 'updateRRSet' => $this->dnsService->upsertRRSet(
                $userId,
                $apiKeyId,
                $domainName,
                $params['subname'] ?? '',
                strtoupper((string) ($params['type'] ?? '')),
                $this->sanitizeRecords($params['records'] ?? []),
                (int) ($params['ttl'] ?? 3600)
            ),
            'deleteRRSet' => [
                'deleted' => $this->dnsService->deleteRRSet(
                    $userId,
                    $apiKeyId,
                    $domainName,
                    $params['subname'] ?? '',
                    strtoupper((string) ($params['type'] ?? ''))
                ),
            ],
            default => throw new RuntimeException('Unbekannte Operation: ' . $operation),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDomainOverview(int $userId): array
    {
        return $this->domains->findByUserId($userId);
    }

    /**
     * @return list<string>
     */
    private function sanitizeRecords(mixed $records): array
    {
        if (is_string($records)) {
            $split = preg_split('/\r?\n/', $records);
            $records = $split !== false ? $split : [];
        }
        if (!is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($value) {
            return trim((string) $value);
        }, $records), static fn($value) => $value !== ''));
    }
}
