-- Migration: user_sessions-Tabelle für Session-Tracking im Admin-Panel.
-- Für bestehende MySQL/MariaDB-Installationen ausführen:
--   mysql -u USER -p DBNAME < sql/mysql/add_user_sessions.sql

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `session_token` VARCHAR(64)      NOT NULL,
    `user_id`       INT UNSIGNED,
    `username`      VARCHAR(255)     NOT NULL DEFAULT '',
    `is_valid`      TINYINT(1)       NOT NULL DEFAULT 1,
    `is_tls`        TINYINT(1)       NOT NULL DEFAULT 0,
    `auth_method`   VARCHAR(32)      NOT NULL DEFAULT '',
    `login_at`      VARCHAR(32),
    `valid_until`   VARCHAR(32),
    `client_ip`     VARCHAR(45),
    `user_agent`    TEXT,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_token`   (`session_token`),
    KEY           `idx_uid`  (`user_id`),
    CONSTRAINT `fk_usess_uid`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
