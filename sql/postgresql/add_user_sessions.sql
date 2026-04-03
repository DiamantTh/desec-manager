-- Migration: user_sessions-Tabelle für Session-Tracking im Admin-Panel.
-- Für bestehende PostgreSQL-Installationen ausführen:
--   psql -U USER -d DBNAME -f sql/postgresql/add_user_sessions.sql

CREATE TABLE IF NOT EXISTS "user_sessions" (
    "id"            SERIAL          PRIMARY KEY,
    "session_token" VARCHAR(64)     NOT NULL,
    "user_id"       INTEGER         REFERENCES "users"("id") ON DELETE CASCADE,
    "username"      VARCHAR(255)    NOT NULL DEFAULT '',
    "is_valid"      BOOLEAN         NOT NULL DEFAULT TRUE,
    "is_tls"        BOOLEAN         NOT NULL DEFAULT FALSE,
    "auth_method"   VARCHAR(32)     NOT NULL DEFAULT '',
    "login_at"      VARCHAR(32),
    "valid_until"   VARCHAR(32),
    "client_ip"     VARCHAR(45),
    "user_agent"    TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS "idx_user_sessions_token"
    ON "user_sessions" ("session_token");

CREATE INDEX IF NOT EXISTS "idx_user_sessions_user_id"
    ON "user_sessions" ("user_id");
