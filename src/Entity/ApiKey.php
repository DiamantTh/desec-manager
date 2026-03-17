<?php
namespace App\Entity;

/**
 * ApiKey Entity - Repräsentiert einen deSEC API-Schlüssel.
 * 
 * Diese Klasse dient als Datenstruktur für API-Schlüssel. Die eigentliche
 * Datenbankanbindung erfolgt über ApiKeyRepository mit Doctrine DBAL.
 * 
 * Datenbank-Tabelle: api_keys
 * - id: INTEGER PRIMARY KEY AUTO_INCREMENT
 * - user_id: INTEGER NOT NULL (FK -> users.id)
 * - name: VARCHAR(255) NOT NULL
 * - api_key: VARCHAR(255) NOT NULL (verschlüsselt)
 * - created_at: DATETIME NOT NULL
 * - last_used: DATETIME NULL
 * - is_active: BOOLEAN DEFAULT TRUE
 */
class ApiKey
{
    private ?int $id = null;
    private User $user;
    private string $name;
    private string $apiKey;
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
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
