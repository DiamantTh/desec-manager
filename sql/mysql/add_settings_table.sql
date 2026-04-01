-- ============================================================
--  deSEC Manager – Migration: settings-Tabelle (MySQL)
-- ============================================================

CREATE TABLE IF NOT EXISTS `settings` (
    `key`         VARCHAR(128) NOT NULL,
    `value`       TEXT         NOT NULL DEFAULT '',
    `type`        VARCHAR(16)  NOT NULL DEFAULT 'string',
    `description` TEXT         DEFAULT NULL,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `description`) VALUES
    ('mail.from_address',       '',        'string', 'Absender-E-Mail-Adresse'),
    ('mail.from_name',          'DeSEC Manager', 'string', 'Absender-Name'),
    ('mail.transport',          'smtp',    'string', 'Mailer-Transport (smtp|sendmail|null)'),
    ('mail.smtp.host',          'localhost','string', 'SMTP-Host'),
    ('mail.smtp.port',          '587',     'int',    'SMTP-Port'),
    ('mail.smtp.encryption',    'tls',     'string', 'TLS-Modus (tls|ssl|leer=kein TLS)'),
    ('mail.smtp.username',      '',        'string', 'SMTP-Benutzername'),
    ('security.login_max_attempts',  '10', 'int',    'Max. Login-Fehlversuche pro IP'),
    ('security.login_window_seconds','900','int',    'Beobachtungszeitraum in Sekunden'),
    ('app.registrations_open',  '0',       'bool',   'Öffentliche Registrierung erlauben'),
    ('app.max_domains_per_user','0',       'int',    '0 = unbegrenzt'),
    ('app.max_keys_per_user',   '0',       'int',    '0 = unbegrenzt');
