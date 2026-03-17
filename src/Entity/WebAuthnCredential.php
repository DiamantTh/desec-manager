<?php
namespace App\Entity;

/**
 * WebAuthnCredential Entity - Repräsentiert ein WebAuthn/FIDO2 Credential.
 * 
 * Diese Klasse dient als Datenstruktur für WebAuthn Credentials. Die eigentliche
 * Datenbankanbindung erfolgt über Doctrine DBAL.
 * 
 * Datenbank-Tabelle: webauthn_credentials
 * - id: INTEGER PRIMARY KEY AUTO_INCREMENT
 * - user_id: INTEGER NOT NULL (FK -> users.id)
 * - credential_id: VARCHAR(255) UNIQUE NOT NULL
 * - public_key: TEXT NOT NULL
 * - sign_count: INTEGER DEFAULT 0
 * - attestation_type: VARCHAR(255) NOT NULL
 * - attachment_type: VARCHAR(255) NOT NULL
 * - created_at: DATETIME NOT NULL
 * - last_used: DATETIME NULL
 * - is_active: BOOLEAN DEFAULT TRUE
 * - name: VARCHAR(255) NOT NULL
 */
class WebAuthnCredential
{
    private ?int $id = null;
    private User $user;
    private string $credentialId;
    private string $publicKey;
    private int $signCount = 0;
    private string $attestationType;
    private string $attachmentType;
    private \DateTime $createdAt;
    private ?\DateTime $lastUsed = null;
    private bool $isActive = true;
    private string $name;

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

    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    public function setCredentialId(string $credentialId): self
    {
        $this->credentialId = $credentialId;
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

    public function getSignCount(): int
    {
        return $this->signCount;
    }

    public function setSignCount(int $signCount): self
    {
        $this->signCount = $signCount;
        return $this;
    }

    public function getAttestationType(): string
    {
        return $this->attestationType;
    }

    public function setAttestationType(string $attestationType): self
    {
        $this->attestationType = $attestationType;
        return $this;
    }

    public function getAttachmentType(): string
    {
        return $this->attachmentType;
    }

    public function setAttachmentType(string $attachmentType): self
    {
        $this->attachmentType = $attachmentType;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
