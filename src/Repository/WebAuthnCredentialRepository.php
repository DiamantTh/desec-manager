<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WebAuthnCredential;
use Doctrine\DBAL\Connection;

/**
 * Repository for FIDO2/WebAuthn credentials.
 *
 * Alle Methoden arbeiten mit der Tabelle `webauthn_credentials`
 * und der WebAuthnCredential-Entity (kein ORM).
 */
class WebAuthnCredentialRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Alle aktiven Credentials eines Nutzers.
     *
     * @return list<WebAuthnCredential>
     */
    public function findByUserId(int $userId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('webauthn_credentials')
            ->where('user_id = :userId AND is_active = 1')
            ->setParameter('userId', $userId)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            fn(array $row): WebAuthnCredential => WebAuthnCredential::fromDbRow($row),
            $rows
        );
    }

    /**
     * Credential anhand der Credential-ID (base64url) laden — inkl. inaktiver.
     */
    public function findByCredentialId(string $credentialId): ?WebAuthnCredential
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('webauthn_credentials')
            ->where('credential_id = :credentialId')
            ->setParameter('credentialId', $credentialId)
            ->executeQuery()
            ->fetchAssociative();

        return $row !== false ? WebAuthnCredential::fromDbRow($row) : null;
    }

    /**
     * Persists a new credential entity.
     */
    public function insert(WebAuthnCredential $credential, int $userId): void
    {
        $row            = $credential->toDbRow();
        $row['user_id'] = $userId;
        $this->connection->insert('webauthn_credentials', $row);
    }

    /**
     * Aktualisiert den Sign-Counter und last_used-Timestamp nach erfolgreicher Authentifizierung.
     */
    public function updateSignCount(string $credentialId, int $signCount): void
    {
        $this->connection->update(
            'webauthn_credentials',
            ['sign_count' => $signCount, 'last_used' => date('Y-m-d H:i:s')],
            ['credential_id' => $credentialId]
        );
    }

    /**
     * Rename a key — only if the user is also the owner.
     */
    public function rename(string $credentialId, int $userId, string $name): void
    {
        $this->connection->update(
            'webauthn_credentials',
            ['name' => $name],
            ['credential_id' => $credentialId, 'user_id' => $userId]
        );
    }

    /**
     * Soft-delete (is_active = 0) — only if the user is also the owner.
     */
    public function deactivate(string $credentialId, int $userId): void
    {
        $this->connection->update(
            'webauthn_credentials',
            ['is_active' => 0],
            ['credential_id' => $credentialId, 'user_id' => $userId]
        );
    }

    /**
     * Anzahl der aktiven Credentials eines Nutzers.
     * Useful to check whether WebAuthn login is possible.
     */
    public function countActiveByUserId(int $userId): int
    {
        $result = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('webauthn_credentials')
            ->where('user_id = :userId AND is_active = 1')
            ->setParameter('userId', $userId)
            ->executeQuery()
            ->fetchOne();

        return (int) $result;
    }
}
