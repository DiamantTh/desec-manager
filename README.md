# deSEC Manager

> DE: Weboberfläche zur Verwaltung von deSEC-Domains, DNS-Records und API-Schlüsseln.
>
> EN: Web interface for managing deSEC domains, DNS records, and API keys.

## Features / Funktionen
- Domain- und Zonenverwaltung mit Anbindung an die deSEC-API / Domain & zone management via the deSEC API
- Verwaltung von DNS-Records inklusive SOA-, A/AAAA-, CNAME-, TXT- und MX-Einträgen / Manage DNS records including SOA, A/AAAA, CNAME, TXT, and MX
- Rollenbasierte Benutzerverwaltung mit WebAuthn-Unterstützung / Role-based user management with WebAuthn support
- Generierung und Rotation von API-Schlüsseln / Generate and rotate API keys
- Systemstatus- und Health-Checks für Integrationen / System status and health checks for integrations

## Requirements / Voraussetzungen
- PHP >= 7.4 mit Erweiterungen `pdo_mysql`, `sodium`, `openssl` / PHP >= 7.4 with `pdo_mysql`, `sodium`, `openssl`
- Composer zum Installieren der Abhängigkeiten / Composer for dependency installation
- MySQL/MariaDB-Datenbank / MySQL or MariaDB database
- Webserver (Apache, Nginx o.ä.) mit PHP-FPM oder mod_php / Web server (Apache, Nginx, etc.) with PHP-FPM or mod_php

## Installation / Setup
1. `composer install`
2. Optional: Konfigurationsvorlagen prüfen (`config/config.php.dist`, `.yaml.dist`, `.toml.dist`, `.ini.dist`) / Review configuration templates
3. `php install.php` ausführen und den Weisungen folgen / Run `php install.php` and follow the prompts
4. Webserver auf das Projektverzeichnis mit `index.php` als Einstieg zeigen / Point your web server to the project root where `index.php` lives
5. Nach erfolgreicher Installation `install.php` entfernen oder sperren / Remove or protect `install.php` after setup

## Configuration / Konfiguration
- Die generierte `config/config.php` enthält alle Einstellungen; zusätzliche Formate dienen als Referenz / The generated `config/config.php` holds runtime settings, other formats act as references.
- Sicherheits-Defaults (Argon2id) können in `config/config.php` überschrieben werden / Argon2id defaults can be overridden in `config/config.php`.
- API-Schlüssel werden verschlüsselt gespeichert; Schlüsselmaterial in `config/config.php` schützen / API keys are stored encrypted; protect the secret material recorded in `config/config.php`.

## Development / Entwicklung
- PHPs Built-in Server: `php -S localhost:8080 -t public/` (nur für Entwicklung) / PHP built-in server (development only).
- Tests sind derzeit nicht automatisiert; Beiträge willkommen / No automated tests yet; contributions welcome.
- Coding-Style: PSR-12 für PHP, ESLint/Prettier für JS falls vorhanden / Suggested style: PSR-12 for PHP, ESLint/Prettier for JS if available.

## Security / Sicherheit
- Admin-Konten sollten WebAuthn aktivieren / Enable WebAuthn for admin accounts.
- Nach dem Setup Standardpasswörter ändern und HTTPS erzwingen / Change default credentials and enforce HTTPS.
- Regelmäßige Sicherheitsupdates für PHP und Composer-Abhängigkeiten einplanen / Plan regular security updates for PHP and Composer dependencies.

## License / Lizenz
- Projektlizenz: bitte mit Projektverantwortlichen klären / Project license: consult the maintainers.
- Abhängigkeiten und ihre Lizenzen siehe Composer-Lock (Kurzfassung unten). / Dependency licenses listed in composer.lock (summary below).

## Dependency Licenses / Abhängigkeitslizenzen
- doctrine/dbal 3.10.2 — MIT
- doctrine/deprecations 1.1.5 — MIT
- doctrine/event-manager 2.0.1 — MIT
- guzzlehttp/guzzle 7.10.0 — MIT
- guzzlehttp/promises 2.3.0 — MIT
- guzzlehttp/psr7 2.8.0 — MIT
- psr/cache 3.0.0 — MIT
- psr/http-client 1.0.3 — MIT
- psr/http-factory 1.1.0 — MIT
- psr/http-message 2.0 — MIT
- psr/log 3.0.2 — MIT
- ralouphie/getallheaders 3.0.3 — MIT
- symfony/deprecation-contracts v3.6.0 — MIT
