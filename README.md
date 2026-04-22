# deSEC Manager

Web interface for managing [deSEC](https://desec.io) domains, DNS records, and API keys.

> Deutsche Version: [README.de.md](README.de.md)

---

## Features

- Domain and zone management via the deSEC API
- Full DNS record management (A/AAAA, CNAME, MX, TXT, SRV, CAA, …)
- International domain name support (IDN/Punycode, RFC 3492) — `müller.eu` is automatically normalised to `xn--mller-kva.eu`
- Role-based user management (admin / regular user) with CSRF protection and rate limiting
- Multi-factor authentication: FIDO2/WebAuthn (passkeys) and TOTP
  - WebAuthn is auto-enabled as soon as `app.domain` is set in `config/config.toml`
- Per-user API key management with encryption at rest
- Per-user theme and language preferences
- Light / Dark mode toggle (user-controlled, no OS follow)
- System status and health-check endpoint (`/status`)
- Supports SQLite, MySQL/MariaDB, and PostgreSQL

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ≥ 8.4 |
| PHP extensions | `pdo_sqlite` or `pdo_mysql` or `pdo_pgsql`, `sodium`, `openssl`, `mbstring`, `intl` |
| Composer | ≥ 2.x |
| Web server | Apache 2.4+ or Nginx (see `docs/server-config/`) |
| Database | SQLite 3, MySQL/MariaDB, or PostgreSQL |

---

## Installation

1. **Clone** the repository and install dependencies:
   ```bash
   git clone ssh://git@git.diath.systems/DiamantTh/desec-manager.git
   cd desec-manager
   composer install --no-dev --optimize-autoloader
   ```

2. **Web installer** — open `https://your-domain/install/` in a browser and follow the steps:
   - Choose database type (SQLite / MySQL / PostgreSQL)
   - Enter database credentials
   - Create the first admin account
   - The installer writes `config/config.toml`, `config/database.toml` and creates all tables

3. **Web server** — point the document root to the project root (where `index.php` lives).  
   Sample configs for Apache and Nginx are in `docs/server-config/`.

4. **Secure the installer** — after setup, restrict or delete the `install/` directory:
   ```bash
   # Restrict via web-server config, or simply remove:
   rm -rf install/
   ```

---

## Configuration

The installer generates two files:

| File | Contents |
|---|---|
| `config/config.toml` | App bootstrap: domain, HTTPS, mail transport, security parameters |
| `config/database.toml` | Database connection (driver, host, name, credentials) |

A fully commented example is at `docs/config/config.toml.example`.

Local overrides (gitignored): `config/config.local.toml`

Never commit secrets to TOML files — use environment variables instead:

| Environment variable | Description |
|---|---|
| `ENCRYPTION_KEY` | 32-byte hex: `php -r "echo sodium_bin2hex(random_bytes(32));"` |
| `MAIL_PASSWORD` | SMTP password |
| `SENTRY_DSN` | Sentry DSN (optional) |

Key settings:

| Section | Key | Description |
|---|---|---|
| `[app]` | `domain` | Public hostname — required for WebAuthn and CSRF |
| `[app]` | `force_https` | Enforce HTTPS redirect (`true` in production) |
| `[cache]` | `adapter` | `filesystem` \| `apcu` \| `redis` \| `memcached` |
| `[security.password]` | `memory_cost` | Argon2id memory in KiB (default: 131072 = 128 MB) |
| `[database]` | `driver` | `pdo_sqlite`, `pdo_mysql`, or `pdo_pgsql` |

Runtime settings (rate limits, FIDO2 parameters, TOTP parameters, mail sender, theme) are managed in the admin interface and stored in the database.

---

## Themes

Two themes are included:

| Theme | Description |
|---|---|
| `default` | Custom theme — dark blue + green palette, Light/Dark toggle |
| `bulma` | Plain Bulma 1.x look without custom colours |

Users can change their theme (and language) in their profile settings.  
Dark mode is **exclusively user-controlled** — it never follows the OS setting.

Custom themes can be placed under `themes/<name>/` with a `theme.json` descriptor.

---

## Development

Run the built-in PHP server (local development only, never in production):

```bash
php -S localhost:8080 -t . index.php
```

> **Note:** `ext-intl` must be enabled (required for IDN/Punycode).  
> Arch Linux: uncomment `extension=intl` in `/etc/php/php.ini`.

Static analysis:

```bash
vendor/bin/phpstan analyse src/
# or short:
composer phpstan
```

Tests:

```bash
composer test
```

---

## Security

- Set `app.domain` in `config/config.toml` — this automatically enables WebAuthn (FIDO2/passkey).
- TOTP is recommended for all accounts, mandatory for admins.
- Enforce HTTPS in production (`force_https = true`); `.htaccess` already sets `Strict-Transport-Security`.
- Set `ENCRYPTION_KEY` exclusively via environment variable, never commit it to any file.
- The `config/` directory is protected from direct web access by `.htaccess`.
- Keep PHP and Composer dependencies up to date.

---

## License

Project license: see repository maintainers.  
Third-party dependency licenses: [docs/dependencies.md](docs/dependencies.md).

