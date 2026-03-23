<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * WebAuthnCredential — represents a stored FIDO2/WebAuthn credential.
 *
 * Used as a data structure for Doctrine DBAL (no ORM).
 *
 * Datenbank-Tabelle: webauthn_credentials
 * ┌──────────────────────┬──────────────────────────────────────────────┐
 * │ Spalte               │ Typ / Bedeutung                              │
 * ├──────────────────────┼──────────────────────────────────────────────┤
 * │ id                   │ INTEGER PK AUTO_INCREMENT                    │
 * │ user_id              │ INTEGER NOT NULL (FK → users.id)             │
 * │ credential_id        │ TEXT UNIQUE NOT NULL (Base64url-kodiert)      │
 * │ public_key_cbor      │ TEXT NOT NULL (CBOR-encoded public key)       │
 * │ sign_count           │ INTEGER DEFAULT 0 (Replay-Schutz)            │
 * │ aaguid               │ VARCHAR(36) NULL (Authenticator-Typ-UUID)    │
 * │ transports           │ TEXT NULL (JSON-Array: usb,nfc,ble,hybrid,…) │
 * │ uv_initialized       │ INTEGER DEFAULT 0 (UV beim Registrieren?)    │
 * │ backup_eligible      │ INTEGER DEFAULT 0 (synced passkey possible?)  │
 * │ backup_state         │ INTEGER DEFAULT 0 (Aktuell synchronisiert?)  │
 * │ attestation_type     │ VARCHAR(32) NOT NULL (none/basic/self/…)     │
 * │ attachment_type      │ VARCHAR(32) NULL (platform/cross-platform)   │
 * │ name                 │ VARCHAR(255) NOT NULL (vom Nutzer vergeben)  │
 * │ is_active            │ INTEGER DEFAULT 1                            │
 * │ created_at           │ DATETIME NOT NULL                            │
 * │ last_used            │ DATETIME NULL                                │
 * └──────────────────────┴──────────────────────────────────────────────┘
 *
 * Feld-Glossar:
 *   aaguid          → Authenticator Attestation GUID: identifiziert Hersteller/Modell
 *                     (z. B. YubiKey 5 Series). Kann in FIDO MDS3 nachgeschlagen werden.
 *   transports      → transmission media: ["usb","nfc","ble","internal","hybrid"].
 *                     Set in allowCredentials.transports → browser selects optimally.
 *   uv_initialized  → Ob beim Registrieren UV (PIN/Biometrie) bereits konfiguriert war.
 *   backup_eligible → BE-Flag: Credential kann cloud-synchronisiert werden (Passkey).
 *   backup_state    → BS flag: credential is currently actually synchronised.
 */
class WebAuthnCredential
{
    private ?int $id = null;
    private int $userId;
    private string $credentialId;
    private string $publicKeyCbor;
    private int $signCount = 0;
    private ?string $aaguid = null;
    /** @var list<string> */
    private array $transports = [];
    private bool $uvInitialized = false;
    private bool $backupEligible = false;
    private bool $backupState = false;
    private string $attestationType = 'none';
    private ?string $attachmentType = null;
    private string $name;
    private bool $isActive = true;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $lastUsed = null;

    public function __construct(string $name, int $userId)
    {
        $this->name      = $name;
        $this->userId    = $userId;
        $this->createdAt = new \DateTimeImmutable();
    }

    // ------------------------------------------------------------------
    // Getter / Setter
    // ------------------------------------------------------------------

    public function getId(): ?int { return $this->id; }

    public function setId(int $id): self { $this->id = $id; return $this; }

    public function getUserId(): int { return $this->userId; }

    public function getCredentialId(): string { return $this->credentialId; }

    public function setCredentialId(string $credentialId): self
    {
        $this->credentialId = $credentialId;
        return $this;
    }

    public function getPublicKeyCbor(): string { return $this->publicKeyCbor; }

    public function setPublicKeyCbor(string $publicKeyCbor): self
    {
        $this->publicKeyCbor = $publicKeyCbor;
        return $this;
    }

    public function getSignCount(): int { return $this->signCount; }

    public function setSignCount(int $signCount): self
    {
        $this->signCount = $signCount;
        return $this;
    }

    /** AAGUID als UUID-String (z. B. "f8a011f3-8c0a-4d15-8006-17111f9edc7d") oder null */
    public function getAaguid(): ?string { return $this->aaguid; }

    public function setAaguid(?string $aaguid): self
    {
        $this->aaguid = $aaguid;
        return $this;
    }

    /** @return list<string> */
    public function getTransports(): array { return $this->transports; }

    /** @param list<string> $transports */
    public function setTransports(array $transports): self
    {
        $this->transports = $transports;
        return $this;
    }

    /**
     * Ob der Authenticator bei der Registrierung UV (PIN/Biometrie)
     * eingerichtet hatte — relevant wenn user_verification = "required".
     */
    public function isUvInitialized(): bool { return $this->uvInitialized; }

    public function setUvInitialized(bool $uvInitialized): self
    {
        $this->uvInitialized = $uvInitialized;
        return $this;
    }

    /** BE-Flag: Credential kann in einer Cloud synchronisiert werden */
    public function isBackupEligible(): bool { return $this->backupEligible; }

    public function setBackupEligible(bool $backupEligible): self
    {
        $this->backupEligible = $backupEligible;
        return $this;
    }

    /** BS-Flag: Credential ist aktuell synchronisiert */
    public function isBackupState(): bool { return $this->backupState; }

    public function setBackupState(bool $backupState): self
    {
        $this->backupState = $backupState;
        return $this;
    }

    /** Attestationstyp: none | self | basic | attca | anonca | ecdaa */
    public function getAttestationType(): string { return $this->attestationType; }

    public function setAttestationType(string $attestationType): self
    {
        $this->attestationType = $attestationType;
        return $this;
    }

    /** platform (eingebaut) | cross-platform (externer Key) | null (unbekannt) */
    public function getAttachmentType(): ?string { return $this->attachmentType; }

    public function setAttachmentType(?string $attachmentType): self
    {
        $this->attachmentType = $attachmentType;
        return $this;
    }

    /** User-assigned name (e.g. "YubiKey 5C", "iPhone Face ID") */
    public function getName(): string { return $this->name; }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function isActive(): bool { return $this->isActive; }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getLastUsed(): ?\DateTimeImmutable { return $this->lastUsed; }

    public function setLastUsed(\DateTimeImmutable $lastUsed): self
    {
        $this->lastUsed = $lastUsed;
        return $this;
    }

    // ------------------------------------------------------------------
    // UX-Hilfsmethoden
    // ------------------------------------------------------------------

    /**
     * Returns a display label suitable for the UI.
     * Zeigt Attachment-Typ, Transport-Medien und Sync-Status an.
     */
    public function getDisplayLabel(): string
    {
        $parts = [$this->name];

        if ($this->attachmentType === 'platform') {
            $parts[] = '(eingebaut)';
        } elseif ($this->attachmentType === 'cross-platform' && $this->transports !== []) {
            $parts[] = '(' . implode('/', $this->transports) . ')';
        }

        if ($this->backupEligible) {
            $parts[] = $this->backupState ? '☁ sync' : '☁ sync-fähig';
        }

        return implode(' ', $parts);
    }

    /**
     * Serialises the entity for Doctrine DBAL (INSERT / UPDATE).
     *
     * @return array<string, mixed>
     */
    public function toDbRow(): array
    {
        return [
            'user_id'          => $this->userId,
            'credential_id'    => $this->credentialId,
            'public_key_cbor'  => $this->publicKeyCbor,
            'sign_count'       => $this->signCount,
            'aaguid'           => $this->aaguid,
            'transports'       => $this->transports !== [] ? json_encode($this->transports) : null,
            'uv_initialized'   => (int)$this->uvInitialized,
            'backup_eligible'  => (int)$this->backupEligible,
            'backup_state'     => (int)$this->backupState,
            'attestation_type' => $this->attestationType,
            'attachment_type'  => $this->attachmentType,
            'name'             => $this->name,
            'is_active'        => (int)$this->isActive,
            'created_at'       => $this->createdAt->format('Y-m-d H:i:s'),
            'last_used'        => $this->lastUsed?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Hydratiert eine Entity aus einer Datenbankzeile (Doctrine DBAL fetch).
     *
     * @param array<string, mixed> $row
     */
    public static function fromDbRow(array $row): self
    {
        $self = new self((string)($row['name'] ?? ''), (int)($row['user_id'] ?? 0));
        $self->id              = isset($row['id']) ? (int)$row['id'] : null;
        $self->credentialId    = (string)($row['credential_id'] ?? '');
        $self->publicKeyCbor   = (string)($row['public_key_cbor'] ?? '');
        $self->signCount       = (int)($row['sign_count'] ?? 0);
        $self->aaguid          = isset($row['aaguid']) ? (string)$row['aaguid'] : null;
        /** @var list<string> $transports */
        $transports            = isset($row['transports'])
            ? (json_decode((string)$row['transports'], true) ?? [])
            : [];
        $self->transports      = $transports;
        $self->uvInitialized   = (bool)($row['uv_initialized'] ?? false);
        $self->backupEligible  = (bool)($row['backup_eligible'] ?? false);
        $self->backupState     = (bool)($row['backup_state'] ?? false);
        $self->attestationType = (string)($row['attestation_type'] ?? 'none');
        $self->attachmentType  = isset($row['attachment_type']) ? (string)$row['attachment_type'] : null;
        $self->isActive        = (bool)($row['is_active'] ?? true);
        $self->createdAt       = new \DateTimeImmutable((string)($row['created_at'] ?? 'now'));
        $self->lastUsed        = isset($row['last_used'])
            ? new \DateTimeImmutable((string)$row['last_used'])
            : null;
        return $self;
    }
}
