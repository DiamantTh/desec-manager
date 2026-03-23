# deSEC Manager

Web interface for managing [deSEC](https://desec.io) domains, DNS records, and API keys.

> Deutsche Version: [README.de.md](README.de.md)

---

## Features

- Domain and zone management via the deSEC API
- Full DNS record management (A/AAAA, CNAME, MX, TXT, SRV, CAA, …)
- Role-based user management (admin / regular user)
- Multi-factor authentication: FIDO2/WebAuthn (passkeys) and TOTP
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
| PHP extensions | `pdo_sqlite` or `pdo_mysql` or `pdo_pgsql`, `sodium`, `openssl`, `mbstring` |
| Composer | ≥ 2.x |
| Web server | Apache 2.4+ or Nginx (see `docs/server-config/`) |
| Database | SQLite 3, MySQL/MariaDB, or PostgreSQL |

---

## Installation

1. **Clone** the repository and install dependencies:
   ```bash
   git clone https://github.com/your-org/desec-manager.git
   cd desec-manager
   composer install --no-dev --optimize-autoloader
   ```

2. **Web installer** — open `https://your-domain/install/` in a browser and follow the steps:
   - Choose database type (SQLite / MySQL / PostgreSQL)
   - Enter database credentials
   - Create the first admin account
   - The installer writes `config/config.php` and creates all tables

3. **Web server** — point the document root to the project root (where `index.php` lives).  
   Sample configs for Apache and Nginx are in `docs/server-config/`.

4. **Secure the installer** — after setup, restrict or delete the `install/` directory:
   ```bash
   # Restrict via web-server config, or simply remove:
   rm -rf install/
   ```

### Existing installations — DB migration

If you are upgrading from a version that pre-dates the TOTP and user-preference columns, run the matching migration script for your database:

| Database | Script |
|---|---|
| MySQL/MariaDB | `sql/mysql/migrate_user_settings.sql` |
| SQLite | `sql/sqlite/migrate_user_settings.sql` |
| PostgreSQL | `sql/postgresql/migrate_user_settings.sql` |

---

## Configuration

The installer generates `config/config.php`.  
Template files (`config/config.php.dist`, `.yaml.dist`, `.toml.dist`, `.ini.dist`) show all available options.

Key settings:

| Section | Key | Description |
|---|---|---|
| `app` | `name` | Application title shown in the UI |
| `theme` | `name` | Default theme (`default`, `bulma`) |
| `database` | `driver` | `pdo_sqlite`, `pdo_mysql`, or `pdo_pgsql` |
| `security` | `argon2_memory_cost` | Argon2id memory cost (KiB) |

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

Run the built-in PHP server (development only):

```bash
php -S localhost:8080 index.php
```

Static analysis:

```bash
vendor/bin/phpstan analyse --level=8 src/
```

---

## Security

- Enable WebAuthn (FIDO2 / passkey) or TOTP for admin accounts.
- Enforce HTTPS in production; `.htaccess` already sets `Strict-Transport-Security`.
- Keep `config/config.php` unreadable from the web. The `.htaccess` in `config/` blocks direct access.
- Keep PHP and Composer dependencies up to date.

---

## License

Project license: see repository maintainers.  
Third-party dependency licenses: [docs/dependencies.md](docs/dependencies.md).

