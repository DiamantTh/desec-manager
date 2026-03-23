-- ============================================================
--  deSEC Manager – Migration: User-Settings-Spalten (MySQL)
--  Fügt fehlende Spalten für TOTP, Theme und Locale hinzu.
--  Für Bestandsinstallationen, die vor Einführung dieser Felder
--  angelegt wurden. Neue Installationen enthalten diese Spalten
--  bereits durch den Installer.
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN `totp_secret`    TEXT          DEFAULT NULL,
    ADD COLUMN `totp_enabled`   TINYINT(1)    NOT NULL DEFAULT 0,
    ADD COLUMN `totp_algorithm` VARCHAR(16)   NOT NULL DEFAULT 'sha256',
    ADD COLUMN `totp_digits`    INT           NOT NULL DEFAULT 8,
    ADD COLUMN `theme`          VARCHAR(64)   NOT NULL DEFAULT 'default',
    ADD COLUMN `locale`         VARCHAR(16)   NOT NULL DEFAULT 'en';
