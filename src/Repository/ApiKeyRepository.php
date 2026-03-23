<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DecryptedApiKey;
use App\Security\EncryptionService;
use App\Security\UserKeyManager;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

class ApiKeyRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EncryptionService $encryption,
        private readonly UserKeyManager $userKeyManager,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByUserId(int $userId, bool $withDecryption = false): array
    {
        $qb = $this->connection->createQueryBuilder();

        $result = $qb->select('*')
            ->from('api_keys')
            ->where('user_id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('created_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        if ($withDecryption) {
            foreach ($result as &$row) {
                $row['api_key'] = $this->encryption->decrypt($row['api_key']);
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $qb = $this->connection->createQueryBuilder();

        $row = $qb->select('*')
            ->from('api_keys')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdForUser(int $id, int $userId): ?array
    {
        $row = $this->findById($id);

        if (!$row || (int) $row['user_id'] !== $userId) {
            return null;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $keyData
     */
    public function create(array $keyData): int
    {
        $sessionKey   = $this->userKeyManager->getRawSessionKey();
        $encryptedKey = $this->encryption->encrypt($keyData['api_key'], $sessionKey);

        $this->connection->insert('api_keys', [
            'user_id' => $keyData['user_id'],
            'name' => $keyData['name'],
            'api_key' => $encryptedKey,
            'created_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            'is_active' => $keyData['is_active'] ?? true,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function updateLastUsed(int $keyId): void
    {
        $this->connection->update(
            'api_keys',
            ['last_used' => $this->clock->now()->format('Y-m-d H:i:s')],
            ['id' => $keyId]
        );
    }

    public function deactivate(int $keyId, int $userId): void
    {
        $this->connection->update(
            'api_keys',
            ['is_active' => false],
            ['id' => $keyId, 'user_id' => $userId]
        );
    }

    /**
     * Entschlüsselt den api_key-Wert einer Zeile.
     * Verwendet den per-User-Session-Key wenn verfügbar, sonst den App-Key.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function decryptKey(array $row): array
    {
        $sessionKey   = $this->userKeyManager->getRawSessionKey();
        $row['api_key'] = $this->encryption->decrypt((string) $row['api_key'], $sessionKey);
        return $row;
    }

    /**
     * Lädt einen API-Key entschlüsselt als Value-Object zurück.
     * Der Klartext-Key wird im Destruktor per sodium_memzero() aus dem RAM gelöscht.
     */
    public function findDecryptedById(int $id, int $userId): ?DecryptedApiKey
    {
        $row = $this->findByIdForUser($id, $userId);
        if ($row === null) {
            return null;
        }

        $sessionKey = $this->userKeyManager->getRawSessionKey();
        $plain      = $this->encryption->decrypt((string) $row['api_key'], $sessionKey);

        return new DecryptedApiKey(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['name'],
            $plain,
        );
    }
}
