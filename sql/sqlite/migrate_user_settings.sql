-- ============================================================
--  deSEC Manager – Migration: User-Settings-Spalten (SQLite)
--  SQLite unterstützt kein Multi-Column ALTER TABLE — jede
--  Spalte wird einzeln hinzugefügt.
-- ============================================================

ALTER TABLE "users" ADD COLUMN "totp_secret"    TEXT         DEFAULT NULL;
ALTER TABLE "users" ADD COLUMN "totp_enabled"   INTEGER      NOT NULL DEFAULT 0;
ALTER TABLE "users" ADD COLUMN "totp_algorithm" VARCHAR(16)  NOT NULL DEFAULT 'sha256';
ALTER TABLE "users" ADD COLUMN "totp_digits"    INTEGER      NOT NULL DEFAULT 8;
ALTER TABLE "users" ADD COLUMN "theme"          VARCHAR(64)  NOT NULL DEFAULT 'default';
ALTER TABLE "users" ADD COLUMN "locale"         VARCHAR(16)  NOT NULL DEFAULT 'en';
