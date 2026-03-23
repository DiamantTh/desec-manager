<?php
namespace App\Entity;

/**
 * UserKey Entity — represents a public key for a user.
 * 
 * This class serves as a data structure for SSH/GPG keys. The actual
 * database connection is handled by Doctrine DBAL.
 * 
 * Datenbank-Tabelle: user_keys
 * - id: INTEGER PRIMARY KEY AUTO_INCREMENT
 * - user_id: INTEGER NOT NULL (FK -> users.id)
 * - public_key: VARCHAR(255) NOT NULL
 * - key_name: VARCHAR(255) NOT NULL
 * - created_at: DATETIME NOT NULL
 * - last_used: DATETIME NULL
 * - is_active: BOOLEAN DEFAULT TRUE
 */
class UserKey
{
    private ?int $id = null;
    private string $publicKey;
    private string $keyName;
    private User $user;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $lastUsed = null;
    private bool $isActive = true;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): self
    {
        $this->publicKey = $publicKey;
        return $this;
    }

    public function getKeyName(): string
    {
        return $this->keyName;
    }

    public function setKeyName(string $keyName): self
    {
        $this->keyName = $keyName;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsed(): ?\DateTimeImmutable
    {
        return $this->lastUsed;
    }

    public function setLastUsed(?\DateTimeImmutable $lastUsed): self
    {
        $this->lastUsed = $lastUsed;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }
}
