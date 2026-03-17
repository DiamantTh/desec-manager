<?php
namespace App\Repository;

use Doctrine\DBAL\Connection;
use App\Database\DatabaseConnection;

class DomainRepository
{
    private Connection $connection;
    
    public function __construct()
    {
        $this->connection = DatabaseConnection::getConnection();
    }
    
    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByUserId(int $userId): array
    {
        $qb = $this->connection->createQueryBuilder();
        
        return $qb->select('*')
            ->from('domains')
            ->where('user_id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('created_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }
    
    /**
     * @param array<string, mixed> $domainData
     */
    public function create(array $domainData): void
    {
        $this->connection->insert('domains', [
            'user_id' => $domainData['user_id'],
            'domain_name' => $domainData['domain_name'],
            'created_at' => $domainData['created_at'] ?? date('Y-m-d H:i:s')
        ]);
    }

    public function delete(int $userId, string $domainName): void
    {
        $this->connection->delete('domains', [
            'user_id' => $userId,
            'domain_name' => $domainName
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $domainName): ?array
    {
        $qb = $this->connection->createQueryBuilder();

        return $qb->select('*')
            ->from('domains')
            ->where('domain_name = :name')
            ->setParameter('name', $domainName)
            ->executeQuery()
            ->fetchAssociative() ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUserAndDomain(int $userId, string $domainName): ?array
    {
        $qb = $this->connection->createQueryBuilder();

        $row = $qb->select('*')
            ->from('domains')
            ->where('user_id = :userId')
            ->andWhere('domain_name = :domain')
            ->setParameter('userId', $userId)
            ->setParameter('domain', $domainName)
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }

    public function countByUserId(int $userId): int
    {
        $qb = $this->connection->createQueryBuilder();

        return (int) $qb->select('COUNT(*) as cnt')
            ->from('domains')
            ->where('user_id = :userId')
            ->setParameter('userId', $userId)
            ->executeQuery()
            ->fetchOne();
    }
}
