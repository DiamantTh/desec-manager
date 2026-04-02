-- ============================================================
--  deSEC Manager – Migration: settings-Tabelle (SQLite)
--  Führe add_domain_tags.sql vorher oder danach aus (unabhängig)
--
--  Speichert Laufzeit-Einstellungen die über das Admin-Interface
--  geändert werden können. Kein Neustart nötig.
--
--  Schlüssel-Konvention: "gruppe.name", z.B. "mail.from_address"
--  Typ (type): string | int | bool | json
-- ============================================================

CREATE TABLE IF NOT EXISTS "settings" (
    "key"         VARCHAR(128) NOT NULL PRIMARY KEY,
    "value"       TEXT         NOT NULL DEFAULT '',
    "type"        VARCHAR(16)  NOT NULL DEFAULT 'string',
    "description" TEXT         DEFAULT NULL,
    "updated_at"  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Standardwerte (werden durch Admin-UI überschreibbar)
INSERT OR IGNORE INTO "settings" ("key", "value", "type", "description") VALUES
    ('mail.from_address',       '',        'string', 'Absender-E-Mail-Adresse'),
    ('mail.from_name',          'DeSEC Manager', 'string', 'Absender-Name'),
    ('mail.transport',          'smtps',   'string', 'Transport: vollständiger DSN oder smtps (Port 465) | smtp (Port 587)'),
    ('mail.smtp.host',          'localhost','string', 'SMTP-Host'),
    ('mail.smtp.port',          '465',     'int',    'SMTP-Port (465 = SMTPS, 587 = STARTTLS)'),
    ('mail.smtp.username',      '',        'string', 'SMTP-Benutzername (Passwort via MAIL_PASSWORD-Umgebungsvariable)'),
    ('security.login_max_attempts',  '10', 'int',    'Max. Login-Fehlversuche pro IP'),
    ('security.login_window_seconds','900','int',    'Beobachtungszeitraum in Sekunden'),
    ('app.registrations_open',  '0',       'bool',   'Öffentliche Registrierung erlauben'),
    ('app.max_domains_per_user','0',       'int',    '0 = unbegrenzt'),
    ('app.max_keys_per_user',   '0',       'int',    '0 = unbegrenzt');
