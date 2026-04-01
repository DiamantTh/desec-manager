-- ============================================================
--  deSEC Manager – Migration: Domain-Tags (SQLite)
--
--  Ermöglicht das Taggen von Domains nach Zweck / Kontext,
--  z.B. "privat", "Kunde A", "Homelab", "Test".
--
--  Struktur:
--    tags          — Definierte Tags eines Users (Name + Farbe)
--    domain_tags   — n:m-Verknüpfung Domain ↔ Tag
--
--  Tags sind user-scoped (tag.user_id), sodass jeder Nutzer
--  seinen eigenen Tag-Namensraum hat.
-- ============================================================

CREATE TABLE IF NOT EXISTS "tags" (
    "id"         INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
    "user_id"    INTEGER      NOT NULL,
    "name"       VARCHAR(64)  NOT NULL,
    "color"      VARCHAR(32)  NOT NULL DEFAULT '#6b7280', -- Tailwind gray-500 als Default
    "created_at" DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE,
    UNIQUE ("user_id", "name")
);

CREATE TABLE IF NOT EXISTS "domain_tags" (
    "domain_id"  INTEGER NOT NULL,
    "tag_id"     INTEGER NOT NULL,
    PRIMARY KEY ("domain_id", "tag_id"),
    FOREIGN KEY ("domain_id") REFERENCES "domains"("id") ON DELETE CASCADE,
    FOREIGN KEY ("tag_id")    REFERENCES "tags"("id")    ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_tags_user_id    ON "tags"("user_id");
CREATE INDEX IF NOT EXISTS idx_domain_tags_tag ON "domain_tags"("tag_id");
