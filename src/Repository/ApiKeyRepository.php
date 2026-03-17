<?php
namespace App\Repository;

use Doctrine\DBAL\Connection;
use App\Database\DatabaseConnection;
use App\Security\EncryptionService;

class ApiKeyRepository
{
    private Connection $connection;
    private EncryptionService $encryption;

    public function __construct()
    {
        $this->connection = DatabaseConnection::getConnection();
        $this->encryption = new EncryptionService();
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
        $encryptedKey = $this->encryption->encrypt($keyData['api_key']);

        $this->connection->insert('api_keys', [
            'user_id' => $keyData['user_id'],
            'name' => $keyData['name'],
            'api_key' => $encryptedKey,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => $keyData['is_active'] ?? true,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function updateLastUsed(int $keyId): void
    {
        $this->connection->update(
            'api_keys',
            ['last_used' => date('Y-m-d H:i:s')],
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
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function decryptKey(array $row): array
    {
        $row['api_key'] = $this->encryption->decrypt($row['api_key']);
        return $row;
    }
}
