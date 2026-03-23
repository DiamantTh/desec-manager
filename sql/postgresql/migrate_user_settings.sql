-- ============================================================
--  deSEC Manager – Migration: User-Settings-Spalten (PostgreSQL)
-- ============================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS totp_secret    TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS totp_enabled   BOOLEAN      NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS totp_algorithm VARCHAR(16)  NOT NULL DEFAULT 'sha256',
    ADD COLUMN IF NOT EXISTS totp_digits    INTEGER      NOT NULL DEFAULT 8,
    ADD COLUMN IF NOT EXISTS theme          VARCHAR(64)  NOT NULL DEFAULT 'default',
    ADD COLUMN IF NOT EXISTS locale         VARCHAR(16)  NOT NULL DEFAULT 'en';
