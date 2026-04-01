-- ============================================================
--  deSEC Manager – Migration: Domain-Tags (MySQL)
-- ============================================================

CREATE TABLE IF NOT EXISTS `tags` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  NOT NULL,
    `name`       VARCHAR(64)   NOT NULL,
    `color`      VARCHAR(32)   NOT NULL DEFAULT '#6b7280',
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tags_user_name` (`user_id`, `name`),
    KEY `idx_tags_user_id` (`user_id`),
    CONSTRAINT `fk_tags_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `domain_tags` (
    `domain_id`  INT UNSIGNED NOT NULL,
    `tag_id`     INT UNSIGNED NOT NULL,
    PRIMARY KEY (`domain_id`, `tag_id`),
    KEY `idx_domain_tags_tag` (`tag_id`),
    CONSTRAINT `fk_dt_domain` FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dt_tag`    FOREIGN KEY (`tag_id`)    REFERENCES `tags`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
