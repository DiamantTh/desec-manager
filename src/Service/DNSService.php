<?php

namespace App\Service;

use App\DeSEC\DeSECClient;
use App\DeSEC\DeSECException;
use App\Repository\ApiKeyRepository;
use App\Repository\DomainRepository;
use RuntimeException;

class DNSService
{
    private ApiKeyRepository $apiKeys;
    private DomainRepository $domains;

    public function __construct()
    {
        $this->apiKeys = new ApiKeyRepository();
        $this->domains = new DomainRepository();
    }

    /**
     * @return array<string, mixed>
     */
    public function createDomain(int $userId, int $apiKeyId, string $domainName): array
    {
        $this->ensureDomainDoesNotExist($domainName);

        [$client, $token] = $this->createClientForUser($userId, $apiKeyId);

        try {
            $response = $client->createDomain($domainName);
            $this->domains->create([
                'user_id' => $userId,
                'domain_name' => $domainName,
            ]);
            $this->apiKeys->updateLastUsed($apiKeyId);
            return $response;
        } finally {
            $this->wipeToken($token);
        }
    }

    public function deleteDomain(int $userId, int $apiKeyId, string $domainName): bool
    {
        $this->assertDomainOwnership($userId, $domainName);
        [$client, $token] = $this->createClientForUser($userId, $apiKeyId);

        try {
            $success = $client->deleteDomain($domainName);
            if ($success) {
                $this->domains->delete($userId, $domainName);
            }
            $this->apiKeys->updateLastUsed($apiKeyId);
            return $success;
        } finally {
            $this->wipeToken($token);
        }
    }

    /**
     * @return array{added: list<string>, removed: list<string>}
     */
    public function syncDomains(int $userId, int $apiKeyId): array
    {
        [$client, $token] = $this->createClientForUser($userId, $apiKeyId);

        try {
            $remote = $client->listDomains();
            $local = $this->domains->findByUserId($userId);

            $remoteNames = array_map(static fn(array $domain) => $domain['name'], $remote);
            $localNames = array_map(static fn(array $domain) => $domain['domain_name'], $local);

            $added = array_diff($remoteNames, $localNames);
            $removed = array_diff($localNames, $remoteNames);

            foreach ($added as $name) {
                $this->domains->create([
                    'user_id' => $userId,
                    'domain_name' => $name,
                ]);
            }

            foreach ($removed as $name) {
                $this->domains->delete($userId, $name);
            }

            $this->apiKeys->updateLastUsed($apiKeyId);

            return [
                'added' => array_values($added),
                'removed' => array_values($removed),
            ];
        } finally {
            $this->wipeToken($token);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRRSets(int $userId, int $apiKeyId, string $domainName): array
    {
        $this->assertDomainOwnership($userId, $domainName);
        [$client, $token] = $this->createClientForUser($userId, $apiKeyId);

        try {
            $rrsets = $client->getRRSets($domainName);
            $this->apiKeys->updateLastUsed($apiKeyId);
            return $rrsets;
        } finally {
            $this->wipeToken($token);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getDomainDetails(int $userId, int $apiKeyId, string $domainName): array
    {
        $this->assertDomainOwnership($userId, $domainName);
        [$client, $token] = $this->createClientForUser($userId, $apiKeyId);

        try {
            $details = $client->getDomain($domainName);
            $this->apiKeys->updateLastUsed($apiKeyId);
            return $details;
        } finally {
            $this->wipeToken($token);
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    public function listRemoteDomains(int $userId, int $apiKeyId): array
    {
        [$client, $token] = $this->createClientForUser($userId, $apiKeyId);

        try {
            $domains = $client->listDomains();
            $this->apiKeys->updateLastUsed($apiKeyId);
            return $domains;
        } finally {
            $this->wipeToken($token);
        }
    }

    /**
     * @param list<string> $records
     * @return array<string, mixed>
     */
    public function upsertRRSet(
        int $userId,
        int $apiKeyId,
        string $domainName,
        string $subName,
        string $type,
        array $records,
        int $ttl
    ): array {
        $this->assertDomainOwnership($userId, $domainName);
        [$client, $token] = $this->createClientForUser($userId, $apiKeyId);

        try {
            try {
                $result = $client->modifyRRSet($domainName, $subName, $type, $records, $ttl);
            } catch (DeSECException $exception) {
                if ($exception->getCode() === 404) {
                    $result = $client->createRRSet($domainName, $subName, $type, $records, $ttl);
                } else {
                    throw $exception;
                }
            }
            $this->apiKeys->updateLastUsed($apiKeyId);
            return $result;
        } finally {
            $this->wipeToken($token);
        }
    }

    public function deleteRRSet(
        int $userId,
        int $apiKeyId,
        string $domainName,
        string $subName,
        string $type
    ): bool {
        $this->assertDomainOwnership($userId, $domainName);
        [$client, $token] = $this->createClientForUser($userId, $apiKeyId);

        try {
            $success = $client->deleteRRSet($domainName, $subName, $type);
            if ($success) {
                $this->apiKeys->updateLastUsed($apiKeyId);
            }
            return $success;
        } finally {
            $this->wipeToken($token);
        }
    }

    private function assertDomainOwnership(int $userId, string $domainName): void
    {
        $domain = $this->domains->findByUserAndDomain($userId, $domainName);
        if (!$domain) {
            throw new RuntimeException('Keine Berechtigung für diese Domain.');
        }
    }

    private function ensureDomainDoesNotExist(string $domainName): void
    {
        if ($this->domains->findByName($domainName)) {
            throw new RuntimeException('Domain ist bereits hinterlegt.');
        }
    }

    /**
     * @return array{0: DeSECClient, 1: string}
     */
    private function createClientForUser(int $userId, int $apiKeyId): array
    {
        $apiKeyRow = $this->apiKeys->findByIdForUser($apiKeyId, $userId);
        if (!$apiKeyRow) {
            throw new RuntimeException('API-Key gehört nicht zu diesem Konto.');
        }
        if (empty($apiKeyRow['is_active'])) {
            throw new RuntimeException('API-Key ist deaktiviert.');
        }

        $apiKeyRow = $this->apiKeys->decryptKey($apiKeyRow);
        $token = $apiKeyRow['api_key'];

        return [new DeSECClient($token), $token];
    }

    private function wipeToken(string $token): void
    {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($token);
        }
    }
}
