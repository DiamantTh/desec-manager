<?php
namespace App\Entity;

/**
 * User Entity - Repräsentiert einen Benutzer im System.
 * 
 * Diese Klasse dient als Datenstruktur für Benutzer. Die eigentliche
 * Datenbankanbindung erfolgt über UserRepository mit Doctrine DBAL.
 * 
 * Datenbank-Tabelle: users
 * - id: INTEGER PRIMARY KEY AUTO_INCREMENT
 * - username: VARCHAR(255) UNIQUE NOT NULL
 * - password_hash: VARCHAR(255) NOT NULL
 * - email: VARCHAR(255) UNIQUE NOT NULL
 * - is_admin: BOOLEAN DEFAULT FALSE
 * - created_at: DATETIME NOT NULL
 * - last_login: DATETIME NULL
 */
class User
{
    private ?int $id = null;
    private string $username;
    private string $passwordHash;
    private string $email;
    private bool $isAdmin = false;
    private \DateTime $createdAt;
    private ?\DateTime $lastLogin = null;

    /** @var array<int, ApiKey> */
    private array $apiKeys = [];

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

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin): self
    {
        $this->isAdmin = $isAdmin;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getLastLogin(): ?\DateTime
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTime $lastLogin): self
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    /**
     * @return array<int, ApiKey>
     */
    public function getApiKeys(): array
    {
        return $this->apiKeys;
    }

    public function addApiKey(ApiKey $apiKey): self
    {
        if (!in_array($apiKey, $this->apiKeys, true)) {
            $this->apiKeys[] = $apiKey;
            $apiKey->setUser($this);
        }
        return $this;
    }
}
