-- ============================================================
--  deSEC Manager – Migration: Domain-Tags (PostgreSQL)
-- ============================================================

CREATE TABLE IF NOT EXISTS "tags" (
    "id"         SERIAL        NOT NULL PRIMARY KEY,
    "user_id"    INTEGER       NOT NULL,
    "name"       VARCHAR(64)   NOT NULL,
    "color"      VARCHAR(32)   NOT NULL DEFAULT '#6b7280',
    "created_at" TIMESTAMP     NOT NULL DEFAULT NOW(),
    UNIQUE ("user_id", "name"),
    CONSTRAINT "fk_tags_user" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS "domain_tags" (
    "domain_id"  INTEGER NOT NULL,
    "tag_id"     INTEGER NOT NULL,
    PRIMARY KEY ("domain_id", "tag_id"),
    CONSTRAINT "fk_dt_domain" FOREIGN KEY ("domain_id") REFERENCES "domains"("id") ON DELETE CASCADE,
    CONSTRAINT "fk_dt_tag"    FOREIGN KEY ("tag_id")    REFERENCES "tags"("id")    ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_tags_user_id    ON "tags"("user_id");
CREATE INDEX IF NOT EXISTS idx_domain_tags_tag ON "domain_tags"("tag_id");
