<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

class UserRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
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
            'created_at' => $this->clock->now()->format('Y-m-d H:i:s')
        ]);
        
        return (int)$this->connection->lastInsertId();
    }
    
    public function updateLastLogin(int $userId): void 
    {
        $this->connection->update('users', 
            ['last_login' => $this->clock->now()->format('Y-m-d H:i:s')],
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

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $this->connection->update(
            'users',
            ['password_hash' => $passwordHash],
            ['id' => $userId]
        );
    }

    public function setActive(int $userId, bool $active): void
    {
        $this->connection->update(
            'users',
            ['is_active' => $active ? 1 : 0],
            ['id' => $userId]
        );
    }

    public function delete(int $userId): void
    {
        $this->connection->delete('users', ['id' => $userId]);
    }

    public function enableTotp(int $userId, string $secret, string $algorithm = 'sha256', int $digits = 8): void
    {
        $this->connection->update(
            'users',
            [
                'totp_secret'    => $secret,
                'totp_enabled'   => 1,
                'totp_algorithm' => $algorithm,
                'totp_digits'    => $digits,
            ],
            ['id' => $userId]
        );
    }

    public function disableTotp(int $userId): void
    {
        $this->connection->update(
            'users',
            ['totp_enabled' => 0, 'totp_secret' => null],
            ['id' => $userId]
        );
    }

    /**
     * Speichert Salt und Wrapped-Key des per-User-Encryption-Keys.
     * Wird bei Anlage eines neuen Users und bei Passwortänderung aufgerufen.
     */
    public function updateWrappedKey(int $userId, string $b64Salt, string $b64WrappedKey): void
    {
        $this->connection->update(
            'users',
            ['enc_key_salt' => $b64Salt, 'enc_key_wrapped' => $b64WrappedKey],
            ['id' => $userId]
        );
    }

    /**
     * Speichert Theme- und Locale-Einstellungen des Benutzers.
     */
    public function updatePreferences(int $userId, string $theme, string $locale): void
    {
        $this->connection->update(
            'users',
            ['theme' => $theme, 'locale' => $locale],
            ['id' => $userId]
        );
    }
}
