<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * User Entity — represents a user in the system.
 *
 * Datenbankanbindung via UserRepository / Doctrine DBAL.
 *
 * Datenbank-Tabelle: users
 * - id            INTEGER PK AUTO_INCREMENT
 * - username      VARCHAR(255) UNIQUE NOT NULL
 * - password_hash VARCHAR(255) NOT NULL
 * - email         VARCHAR(255) UNIQUE NOT NULL
 * - is_admin      INTEGER DEFAULT 0
 * - is_super_admin INTEGER DEFAULT 0
 * - totp_secret   TEXT NULL        (Base32, bei NULL = TOTP deaktiviert)
 * - totp_enabled  INTEGER DEFAULT 0
 * - totp_algorithm VARCHAR(16) DEFAULT 'sha256'  (sha1|sha256|sha512)
 * - totp_digits   INTEGER DEFAULT 8
 * - created_at    DATETIME NOT NULL
 * - last_login    DATETIME NULL
 */
class User
{
    private ?int $id = null;
    private string $username;
    private string $passwordHash;
    private string $email;
    private bool $isAdmin = false;
    private bool $isSuperAdmin = false;

    // TOTP
    private ?string $totpSecret = null;
    private bool $totpEnabled = false;
    private string $totpAlgorithm = 'sha256';
    private int $totpDigits = 8;

    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $lastLogin = null;

    /** @var array<int, ApiKey> */
    private array $apiKeys = [];

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

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function setIsSuperAdmin(bool $isSuperAdmin): self
    {
        $this->isSuperAdmin = $isSuperAdmin;
        return $this;
    }

    // ------------------------------------------------------------------
    // TOTP
    // ------------------------------------------------------------------

    /** Base32-kodiertes TOTP-Secret; null = TOTP nicht eingerichtet */
    public function getTotpSecret(): ?string { return $this->totpSecret; }

    public function setTotpSecret(?string $totpSecret): self
    {
        $this->totpSecret = $totpSecret;
        return $this;
    }

    public function isTotpEnabled(): bool { return $this->totpEnabled; }

    public function setTotpEnabled(bool $totpEnabled): self
    {
        $this->totpEnabled = $totpEnabled;
        return $this;
    }

    /** sha1 | sha256 | sha512 */
    public function getTotpAlgorithm(): string { return $this->totpAlgorithm; }

    public function setTotpAlgorithm(string $totpAlgorithm): self
    {
        $this->totpAlgorithm = $totpAlgorithm;
        return $this;
    }

    /** Anzahl der Ziffern im generierten Code (Standard: 8) */
    public function getTotpDigits(): int { return $this->totpDigits; }

    public function setTotpDigits(int $totpDigits): self
    {
        $this->totpDigits = $totpDigits;
        return $this;
    }

    // ------------------------------------------------------------------

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastLogin(): ?\DateTimeImmutable
    {
        return $this->lastLogin;
    }

    public function setLastLogin(\DateTimeImmutable $lastLogin): self
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
