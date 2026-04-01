# deSEC Manager

Weboberfläche zur Verwaltung von [deSEC](https://desec.io)-Domains, DNS-Records und API-Schlüsseln.

> English version: [README.md](README.md)

---

## Funktionen

- Domain- und Zonenverwaltung über die deSEC-API
- Vollständige DNS-Record-Verwaltung (A/AAAA, CNAME, MX, TXT, SRV, CAA, …)
- Unterstützung internationaler Domain-Namen (IDN/Punycode, RFC 3492) — `müller.eu` wird automatisch zu `xn--mller-kva.eu` normalisiert
- Rollenbasierte Benutzerverwaltung (Admin / normaler Benutzer) mit CSRF-Schutz und Rate-Limiting
- Zwei-Faktor-Authentifizierung: FIDO2/WebAuthn (Passkeys) und TOTP
  - WebAuthn wird automatisch aktiviert, sobald `app.domain` in `config/config.toml` gesetzt ist
- API-Schlüssel-Verwaltung pro Benutzer mit Verschlüsselung im Ruhezustand
- Individuelle Theme- und Spracheinstellungen pro Benutzer
- Light-/Dark-Mode-Umschalter (benutzergesteuert, kein OS-Follow)
- Systemstatus- und Health-Check-Endpunkt (`/status`)
- Unterstützt SQLite, MySQL/MariaDB und PostgreSQL

---

## Voraussetzungen

| Anforderung | Version |
|---|---|
| PHP | ≥ 8.4 |
| PHP-Erweiterungen | `pdo_sqlite` oder `pdo_mysql` oder `pdo_pgsql`, `sodium`, `openssl`, `mbstring`, `intl` |
| Composer | ≥ 2.x |
| Webserver | Apache 2.4+ oder Nginx (siehe `docs/server-config/`) |
| Datenbank | SQLite 3, MySQL/MariaDB oder PostgreSQL |

---

## Installation

1. **Repository klonen** und Abhängigkeiten installieren:
   ```bash
   git clone https://github.com/your-org/desec-manager.git
   cd desec-manager
   composer install --no-dev --optimize-autoloader
   ```

2. **Web-Installer** — `https://your-domain/install/` im Browser öffnen und den Schritten folgen:
   - Datenbanktyp wählen (SQLite / MySQL / PostgreSQL)
   - Datenbankzugangsdaten eingeben
   - Ersten Admin-Account anlegen
   - Der Installer schreibt `config/config.toml`, `config/database.toml` und erstellt alle Tabellen

3. **Webserver** — Document-Root auf das Projektverzeichnis (wo `index.php` liegt) zeigen.  
   Beispielkonfigurationen für Apache und Nginx liegen unter `docs/server-config/`.

4. **Installer absichern** — Nach dem Setup das `install/`-Verzeichnis einschränken oder löschen:
   ```bash
   # Über Webserver-Konfiguration sperren oder einfach löschen:
   rm -rf install/
   ```

### Bestehende Installationen — DB-Migration

Für Upgrades von Versionen, die TOTP und Benutzerpräferenzen noch nicht kennen, bitte das passende Migrationsskript ausführen:

| Datenbank | Skript |
|---|---|
| MySQL/MariaDB | `sql/mysql/migrate_user_settings.sql` |
| SQLite | `sql/sqlite/migrate_user_settings.sql` |
| PostgreSQL | `sql/postgresql/migrate_user_settings.sql` |

---

## Konfiguration

Der Installer erzeugt zwei Dateien:

| Datei | Inhalt |
|---|---|
| `config/config.toml` | App-Bootstrap: Domain, HTTPS, Mail-Transport, Sicherheitsparameter |
| `config/database.toml` | Datenbankverbindung (Treiber, Host, Name, Zugangsdaten) |

Ein vollständig kommentiertes Beispiel liegt unter `docs/config/config.toml.example`.

Lokale Überschreibungen (gitignored): `config/config.local.toml`

Secrets **niemals** in TOML-Dateien committen — stattdessen Umgebungsvariablen nutzen:

| Umgebungsvariable | Beschreibung |
|---|---|
| `ENCRYPTION_KEY` | 32-Byte-Hex: `php -r "echo sodium_bin2hex(random_bytes(32));"` |
| `MAIL_PASSWORD` | SMTP-Passwort |
| `SENTRY_DSN` | Sentry DSN (optional) |

Wichtige Einstellungen:

| Abschnitt | Schlüssel | Beschreibung |
|---|---|---|
| `[app]` | `domain` | Öffentlicher Hostname — Pflicht für WebAuthn und CSRF |
| `[app]` | `force_https` | HTTPS-Redirect erzwingen (`true` in Produktion) |
| `[cache]` | `adapter` | `filesystem` \| `apcu` \| `redis` \| `memcached` |
| `[security.password]` | `memory_cost` | Argon2id-Speicher in KiB (Standard: 131072 = 128 MB) |
| `[database]` | `driver` | `pdo_sqlite`, `pdo_mysql` oder `pdo_pgsql` |

Runtime-Einstellungen (Rate-Limits, FIDO2-Parameter, TOTP-Parameter, Mail-Absender, Theme) werden im Admin-Interface verwaltet und in der Datenbank gespeichert.

---

## Themes

Zwei Themes sind enthalten:

| Theme | Beschreibung |
|---|---|
| `default` | Eigenes deSEC-Manager-Theme — Dunkelblau + Grün, Light/Dark-Umschalter |
| `bulma` | Schlicht mit Standard-Bulma-1.x-Farben |

Benutzer können Theme und Sprache in den Profileinstellungen ändern.  
Der Dark Mode ist **ausschließlich benutzergesteuert** — er folgt nie der OS-Einstellung.

Eigene Themes können unter `themes/<name>/` mit einer `theme.json`-Beschreibung abgelegt werden.

---

## Entwicklung

PHP-Built-in-Server starten (nur lokal, nie in Produktion):

```bash
php -S localhost:8080 -t . index.php
```

> **Hinweis:** `ext-intl` muss aktiviert sein (wird für IDN/Punycode benötigt).  
> Arch Linux: `extension=intl` in `/etc/php/php.ini` einkommentieren.

Statische Analyse:

```bash
vendor/bin/phpstan analyse src/
# oder kurz:
composer phpstan
```

Tests:

```bash
composer test
```

---

## Sicherheit

- `app.domain` in `config/config.toml` setzen — aktiviert WebAuthn (FIDO2/Passkey) automatisch.
- TOTP für alle Konten empfohlen, zwingend für Admins.
- HTTPS in der Produktion erzwingen (`force_https = true`); `.htaccess` setzt bereits `Strict-Transport-Security`.
- `ENCRYPTION_KEY` ausschließlich über Umgebungsvariable setzen, nie in Dateien committen.
- `config/` ist per `.htaccess` vor direktem Web-Zugriff geschützt.
- PHP und Composer-Abhängigkeiten regelmäßig aktualisieren.

---

## Lizenz

Projektlizenz: beim Projektverantwortlichen erfragen.  
Lizenzen der Drittanbieter-Abhängigkeiten: [docs/dependencies.md](docs/dependencies.md).
