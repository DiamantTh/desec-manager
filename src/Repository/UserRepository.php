<?php
namespace App\Repository;

use Doctrine\DBAL\Connection;
use App\Database\DatabaseConnection;

class UserRepository 
{
    private Connection $connection;
    
    public function __construct() 
    {
        $this->connection = DatabaseConnection::getConnection();
    }
    
    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array 
    {
        $qb = $this->connection->createQueryBuilder();
        
        return $qb->select('*')
            ->from('users')
            ->where('username = :username')
            ->setParameter('username', $username)
            ->executeQuery()
            ->fetchAssociative() ?: null;
    }
    
    /**
     * @param array<string, mixed> $userData
     */
    public function create(array $userData): int 
    {
        $this->connection->insert('users', [
            'username' => $userData['username'],
            'password_hash' => $userData['password_hash'],
            'email' => $userData['email'],
            'is_admin' => $userData['is_admin'] ?? false,
            'is_active' => $userData['is_active'] ?? true,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return (int)$this->connection->lastInsertId();
    }
    
    public function updateLastLogin(int $userId): void 
    {
        $this->connection->update('users', 
            ['last_login' => date('Y-m-d H:i:s')],
            ['id' => $userId]
        );
    }
    
    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array 
    {
        $qb = $this->connection->createQueryBuilder();
        
        return $qb->select('*')
            ->from('users')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->executeQuery()
            ->fetchAssociative() ?: null;
    }
    
    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array 
    {
        $qb = $this->connection->createQueryBuilder();
        
        return $qb->select('*')
            ->from('users')
            ->where('email = :email')
            ->setParameter('email', $email)
            ->executeQuery()
            ->fetchAssociative() ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAdmins(): array
    {
        $qb = $this->connection->createQueryBuilder();

        return $qb->select('id', 'username', 'email', 'created_at', 'last_login')
            ->from('users')
            ->where('is_admin = 1')
            ->orderBy('username', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function isUsernameAvailable(string $username): bool
    {
        return $this->findByUsername($username) === null;
    }

    public function isEmailAvailable(string $email): bool
    {
        return $this->findByEmail($email) === null;
    }
}
