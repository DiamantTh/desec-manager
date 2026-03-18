-- SQLite — MFA-Migration (FIDO2 + TOTP)
-- Führe dieses Skript einmalig nach dem Update aus.
-- SQLite unterstützt kein RENAME COLUMN vor Version 3.25.0 (2018-09-15).
-- Prüfe deine Version: SELECT sqlite_version();

-- =========================================================================
-- Tabelle: webauthn_credentials
-- Neue Spalten für FIDO2-Metadaten (AAGUID, Transports, UV, Backup-Flags)
-- =========================================================================

-- Credential ID als base64url-kodierter String (eindeutiger Index)
-- Früher: public_key (CBOR-Blob), jetzt: public_key_cbor
ALTER TABLE webauthn_credentials ADD COLUMN public_key_cbor TEXT;

-- AAGUID: UUID des Authenticator-Modells (z.B. YubiKey 5 Series)
ALTER TABLE webauthn_credentials ADD COLUMN aaguid TEXT NULL;

-- Transports: JSON-Array mit USB/NFC/BLE/internal/hybrid
ALTER TABLE webauthn_credentials ADD COLUMN transports TEXT NULL;

-- UV-Flag: war User Verification (PIN/Biometrie) bei Registrierung gesetzt?
ALTER TABLE webauthn_credentials ADD COLUMN uv_initialized INTEGER NOT NULL DEFAULT 0;

-- BE-Flag: Credential kann cloud-synchronisiert werden (Passkey)
ALTER TABLE webauthn_credentials ADD COLUMN backup_eligible INTEGER NOT NULL DEFAULT 0;

-- BS-Flag: Credential ist aktuell synchronisiert
ALTER TABLE webauthn_credentials ADD COLUMN backup_state INTEGER NOT NULL DEFAULT 0;

-- Attestationstyp: none | self | basic | attca | anonca
ALTER TABLE webauthn_credentials ADD COLUMN attestation_type TEXT NOT NULL DEFAULT 'none';

-- Attachment: platform (eingebaut) | cross-platform (externer Key) | NULL
ALTER TABLE webauthn_credentials ADD COLUMN attachment_type TEXT NULL;

-- Bestehende Zeilen: public_key → public_key_cbor migrieren
-- (SQLite kann keine Spalten umbenennen in alten Versionen — daher Kopieransatz)
UPDATE webauthn_credentials SET public_key_cbor = public_key WHERE public_key_cbor IS NULL;

-- =========================================================================
-- Tabelle: users
-- Neue Spalten für TOTP-Unterstützung
-- =========================================================================

-- Base32-kodiertes TOTP-Secret (NULL = TOTP nicht konfiguriert)
ALTER TABLE users ADD COLUMN totp_secret TEXT NULL;

-- 1 = TOTP aktiv und verifiziert, 0 = deaktiviert
ALTER TABLE users ADD COLUMN totp_enabled INTEGER NOT NULL DEFAULT 0;

-- TOTP-Hash-Algorithmus: sha1 | sha256 | sha512
ALTER TABLE users ADD COLUMN totp_algorithm TEXT NOT NULL DEFAULT 'sha256';

-- Anzahl der TOTP-Stellen (Standard: 8, RFC-Standard wäre 6)
ALTER TABLE users ADD COLUMN totp_digits INTEGER NOT NULL DEFAULT 8;
