-- MySQL/MariaDB — MFA-Migration (FIDO2 + TOTP)
-- Führe dieses Skript einmalig nach dem Update aus.
-- Kompatibel mit MySQL 5.7+ und MariaDB 10.3+.

-- =========================================================================
-- Tabelle: webauthn_credentials
-- Neue Spalten für FIDO2-Metadaten (AAGUID, Transports, UV, Backup-Flags)
-- =========================================================================

ALTER TABLE `webauthn_credentials`
    -- CBOR-kodierter Public Key (Umbenennung von public_key)
    ADD COLUMN `public_key_cbor`  MEDIUMBLOB      NULL          AFTER `credential_id`,

    -- AAGUID: UUID des Authenticator-Modells (z.B. "f8a011f3-8c0a-4d15-8006-17111f9edc7d")
    ADD COLUMN `aaguid`           VARCHAR(36)     NULL          AFTER `public_key_cbor`,

    -- Transports: JSON-Array ["usb","nfc","ble","internal","hybrid"]
    ADD COLUMN `transports`       TEXT            NULL          AFTER `aaguid`,

    -- UV-Flag: war User Verification bei Registrierung gesetzt?
    ADD COLUMN `uv_initialized`   TINYINT(1)      NOT NULL DEFAULT 0 AFTER `transports`,

    -- BE-Flag: Passkey-fähig (cloud-synchronisierbar)
    ADD COLUMN `backup_eligible`  TINYINT(1)      NOT NULL DEFAULT 0 AFTER `uv_initialized`,

    -- BS-Flag: Aktuell synchronisiert
    ADD COLUMN `backup_state`     TINYINT(1)      NOT NULL DEFAULT 0 AFTER `backup_eligible`,

    -- Attestationstyp: none | self | basic | attca | anonca | ecdaa
    ADD COLUMN `attestation_type` VARCHAR(32)     NOT NULL DEFAULT 'none' AFTER `backup_state`,

    -- Attachment: platform | cross-platform | NULL
    ADD COLUMN `attachment_type`  VARCHAR(32)     NULL          AFTER `attestation_type`;

-- Bestehende Zeilen: public_key → public_key_cbor migrieren
UPDATE `webauthn_credentials` SET `public_key_cbor` = `public_key` WHERE `public_key_cbor` IS NULL;

-- Altes public_key-Feld entfernen (erst nach Migration und Verifikation)
-- ALTER TABLE `webauthn_credentials` DROP COLUMN `public_key`;

-- =========================================================================
-- Tabelle: users
-- Neue Spalten für TOTP-Unterstützung
-- =========================================================================

ALTER TABLE `users`
    -- Base32-kodiertes TOTP-Secret (NULL = nicht eingerichtet)
    ADD COLUMN `totp_secret`    TEXT            NULL          AFTER `password`,

    -- 1 = TOTP aktiv und verifiziert
    ADD COLUMN `totp_enabled`   TINYINT(1)      NOT NULL DEFAULT 0 AFTER `totp_secret`,

    -- TOTP-Algorithmus: sha1 | sha256 | sha512
    ADD COLUMN `totp_algorithm` VARCHAR(16)     NOT NULL DEFAULT 'sha256' AFTER `totp_enabled`,

    -- Anzahl TOTP-Stellen (8 = unser Standard)
    ADD COLUMN `totp_digits`    TINYINT         NOT NULL DEFAULT 8 AFTER `totp_algorithm`;
