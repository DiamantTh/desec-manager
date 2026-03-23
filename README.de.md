# deSEC Manager

Weboberfläche zur Verwaltung von [deSEC](https://desec.io)-Domains, DNS-Records und API-Schlüsseln.

> English version: [README.md](README.md)

---

## Funktionen

- Domain- und Zonenverwaltung über die deSEC-API
- Vollständige DNS-Record-Verwaltung (A/AAAA, CNAME, MX, TXT, SRV, CAA, …)
- Rollenbasierte Benutzerverwaltung (Admin / normaler Benutzer)
- Zwei-Faktor-Authentifizierung: FIDO2/WebAuthn (Passkeys) und TOTP
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
| PHP-Erweiterungen | `pdo_sqlite` oder `pdo_mysql` oder `pdo_pgsql`, `sodium`, `openssl`, `mbstring` |
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
   - Der Installer schreibt `config/config.php` und erstellt alle Tabellen

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

Der Installer erzeugt `config/config.php`.  
Vorlagedateien (`config/config.php.dist`, `.yaml.dist`, `.toml.dist`, `.ini.dist`) zeigen alle verfügbaren Optionen.

Wichtige Einstellungen:

| Abschnitt | Schlüssel | Beschreibung |
|---|---|---|
| `app` | `name` | Anwendungstitel in der Benutzeroberfläche |
| `theme` | `name` | Standard-Theme (`default`, `bulma`) |
| `database` | `driver` | `pdo_sqlite`, `pdo_mysql` oder `pdo_pgsql` |
| `security` | `argon2_memory_cost` | Argon2id-Speicherkosten (KiB) |

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

PHP-Built-in-Server starten (nur für Entwicklung):

```bash
php -S localhost:8080 index.php
```

Statische Analyse:

```bash
vendor/bin/phpstan analyse --level=8 src/
```

---

## Sicherheit

- WebAuthn (FIDO2 / Passkey) oder TOTP für Admin-Konten aktivieren.
- HTTPS in der Produktion erzwingen; `.htaccess` setzt bereits `Strict-Transport-Security`.
- `config/config.php` vor Web-Zugriff schützen. Die `.htaccess` im `config/`-Verzeichnis blockiert direkten Zugriff.
- PHP und Composer-Abhängigkeiten regelmäßig aktualisieren.

---

## Lizenz

Projektlizenz: beim Projektverantwortlichen erfragen.  
Lizenzen der Drittanbieter-Abhängigkeiten: [docs/dependencies.md](docs/dependencies.md).
