<?php
namespace App\Entity;

/**
 * UserKey Entity - Repräsentiert einen öffentlichen Schlüssel für einen Benutzer.
 * 
 * Diese Klasse dient als Datenstruktur für SSH/GPG Schlüssel. Die eigentliche
 * Datenbankanbindung erfolgt über Doctrine DBAL.
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
    private \DateTime $createdAt;
    private ?\DateTime $lastUsed = null;
    private bool $isActive = true;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getLastUsed(): ?\DateTime
    {
        return $this->lastUsed;
    }

    public function setLastUsed(?\DateTime $lastUsed): self
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
