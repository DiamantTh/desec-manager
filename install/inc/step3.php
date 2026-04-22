<?php

declare(strict_types=1);

/**
 * Installer – Phase 3: Bestätigung + Ausführung
 * Handler + View
 */

/** @return string[] */
function processStep3(): array
{
    $db    = $_SESSION['install_db']    ?? null;
    $admin = $_SESSION['install_admin'] ?? null;
    $app   = $_SESSION['install_app']   ?? null;

    if (!$db || !$admin || !$app) {
        $_SESSION['install_step'] = 1;
        return [t('step3.session_lost')];
    }

    try {
        // ── Datenbankverbindung per Doctrine DBAL ──────────────────────────
        $params = match ($db['driver']) {
            'pdo_sqlite' => [
                'driver' => 'pdo_sqlite',
                'path'   => $db['path'],
            ],
            'pdo_pgsql' => [
                'driver'   => 'pdo_pgsql',
                'host'     => $db['host'],
                'port'     => (int) $db['port'],
                'dbname'   => $db['name'],
                'user'     => $db['user'],
                'password' => $db['pass'] ?? '',
            ],
            default => [
                'driver'   => 'pdo_mysql',
                'host'     => $db['host'],
                'port'     => (int) $db['port'],
                'dbname'   => $db['name'],
                'user'     => $db['user'],
                'password' => $db['pass'],
                'charset'  => 'utf8mb4',
            ],
        };

        $conn   = \Doctrine\DBAL\DriverManager::getConnection($params);
        $schema = new \Doctrine\DBAL\Schema\Schema();

        // ── Tabellen anlegen ──────────────────────────────────────────────
        $t = $schema->createTable('users');
        foreach ([
            ['id',            'integer', ['autoincrement' => true]],
            ['username',      'string',  ['length' => 255]],
            ['password_hash', 'string',  ['length' => 255]],
            ['email',         'string',  ['length' => 255]],
            ['created_at',    'string',  ['length' => 32, 'notnull' => false]],
            ['last_login',    'string',  ['length' => 32, 'notnull' => false]],
            ['is_active',     'boolean', ['default' => true]],
            ['is_admin',      'boolean', ['default' => false]],
            ['totp_secret',   'text',    ['notnull' => false]],
            ['totp_enabled',  'boolean', ['default' => false]],
            ['totp_algorithm','string',  ['length' => 16, 'default' => 'sha256']],
            ['totp_digits',   'integer', ['default' => 8]],
            ['theme',         'string',  ['length' => 64, 'default' => 'default']],
            ['locale',        'string',  ['length' => 16, 'default' => 'en']],
        ] as [$col, $type, $opts]) {
            $t->addColumn($col, $type, $opts);
        }
        $t->setPrimaryKey(['id']);
        $t->addUniqueIndex(['username']);
        $t->addUniqueIndex(['email']);

        $k = $schema->createTable('api_keys');
        foreach ([
            ['id',        'integer', ['autoincrement' => true]],
            ['user_id',   'integer', []],
            ['name',      'string',  ['length' => 255]],
            ['api_key',   'string',  ['length' => 255]],
            ['created_at','string',  ['length' => 32, 'notnull' => false]],
            ['last_used', 'string',  ['length' => 32, 'notnull' => false]],
            ['is_active', 'boolean', ['default' => true]],
        ] as [$col, $type, $opts]) {
            $k->addColumn($col, $type, $opts);
        }
        $k->setPrimaryKey(['id']);
        $k->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

        $d = $schema->createTable('domains');
        foreach ([
            ['id',          'integer', ['autoincrement' => true]],
            ['user_id',     'integer', []],
            ['domain_name', 'string',  ['length' => 255]],
            ['created_at',  'string',  ['length' => 32, 'notnull' => false]],
        ] as [$col, $type, $opts]) {
            $d->addColumn($col, $type, $opts);
        }
        $d->setPrimaryKey(['id']);
        $d->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
        $d->addUniqueIndex(['domain_name']);

        $s = $schema->createTable('user_sessions');
        foreach ([
            ['id',            'integer', ['autoincrement' => true]],
            ['session_token', 'string',  ['length' => 64]],
            ['user_id',       'integer', ['notnull' => false]],
            ['username',      'string',  ['length' => 255, 'default' => '']],
            ['is_valid',      'boolean', ['default' => true]],
            ['is_tls',        'boolean', ['default' => false]],
            ['auth_method',   'string',  ['length' => 32, 'default' => '']],
            ['login_at',      'string',  ['length' => 32, 'notnull' => false]],
            ['valid_until',   'string',  ['length' => 32, 'notnull' => false]],
            ['client_ip',     'string',  ['length' => 45, 'notnull' => false]],
            ['user_agent',    'text',    ['notnull' => false]],
        ] as [$col, $type, $opts]) {
            $s->addColumn($col, $type, $opts);
        }
        $s->setPrimaryKey(['id']);
        $s->addUniqueIndex(['session_token']);
        $s->addIndex(['user_id']);
        $s->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);

        foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
            $conn->executeStatement($sql);
        }

        // ── Admin-Benutzer anlegen ────────────────────────────────────────
        $hash = password_hash(
            $admin['password'],
            PASSWORD_ARGON2ID,
            ['memory_cost' => 131072, 'time_cost' => 4, 'threads' => 4]
        );
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $conn->insert('users', [
            'username'      => $admin['username'],
            'password_hash' => $hash,
            'email'         => $admin['email'],
            'is_admin'      => 1,
            'is_active'     => 1,
            'created_at'    => $now,
        ]);

        // ── Verschlüsselungs-Key generieren ──────────────────────────────
        $encKey = base64_encode(random_bytes(32));

        // ── config.local.toml ─────────────────────────────────────────────
        $cfgDir = PROJECT_ROOT . '/config';
        if (!is_dir($cfgDir)) {
            mkdir($cfgDir, 0750, true);
        }

        $forceHttps       = $app['https'] ? 'true' : 'false';
        $escapedEncKey    = addcslashes($encKey,        '"\\');
        $escapedDomain    = addcslashes($app['domain'], '"\\');
        $escapedAppName   = addcslashes($app['name'],   '"\\');
        $escapedAppTheme  = addcslashes($app['theme'],  '"\\');

        $localToml = <<<TOML
# DeSEC Manager — Lokale Konfiguration (auto-generiert am {$now})
# NIEMALS ins Git einpflegen!

[security]
encryption_key = "{$escapedEncKey}"

[app]
domain      = "{$escapedDomain}"
force_https = {$forceHttps}
debug       = false

[application]
name = "{$escapedAppName}"

[theme]
name = "{$escapedAppTheme}"
TOML;

        $localTomlFile = $cfgDir . '/config.local.toml';
        if (file_exists($localTomlFile)) {
            copy($localTomlFile, $localTomlFile . '.bak.' . date('Y-m-d-H-i-s'));
        }
        file_put_contents($localTomlFile, $localToml);
        chmod($localTomlFile, 0600);

        // ── database.toml ─────────────────────────────────────────────────
        if ($db['driver'] === 'pdo_sqlite') {
            $escapedPath = addcslashes($db['path'], '"\\');
            $dbToml = <<<TOML
# DeSEC Manager — Datenbankkonfiguration (auto-generiert am {$now})

[database]
driver = "pdo_sqlite"

[database.sqlite]
path = "{$escapedPath}"
TOML;
        } else {
            $dbDriver  = addcslashes($db['driver'],    '"\\');
            $dbHost    = addcslashes($db['host'],      '"\\');
            $dbPort    = (int) $db['port'];
            $dbName    = addcslashes($db['name'],      '"\\');
            $dbUser    = addcslashes($db['user'],      '"\\');
            $dbPass    = addcslashes($db['pass'] ?? '', '"\\');
            $dbCharset = $db['driver'] === 'pdo_mysql' ? 'utf8mb4' : '';

            $dbToml = <<<TOML
# DeSEC Manager — Datenbankkonfiguration (auto-generiert am {$now})
# Passwort besser via DB_PASSWORD-Umgebungsvariable statt in dieser Datei.

[database]
driver   = "{$dbDriver}"
host     = "{$dbHost}"
port     = {$dbPort}
name     = "{$dbName}"
user     = "{$dbUser}"
password = "{$dbPass}"
TOML;
            if ($dbCharset !== '') {
                $dbToml .= "\ncharset   = \"{$dbCharset}\"";
                $dbToml .= "\ncollation = \"utf8mb4_unicode_ci\"";
            }
        }

        $dbTomlFile = $cfgDir . '/database.toml';
        if (file_exists($dbTomlFile)) {
            copy($dbTomlFile, $dbTomlFile . '.bak.' . date('Y-m-d-H-i-s'));
        }
        file_put_contents($dbTomlFile, $dbToml);
        chmod($dbTomlFile, 0600);

        // ── Lock-Datei schreiben ──────────────────────────────────────────
        file_put_contents(LOCK_FILE, date('Y-m-d H:i:s') . "\n");

        // ── Session abschliessen ──────────────────────────────────────────
        $_SESSION['install_result'] = [
            'admin_user' => $admin['username'],
            'admin_pass' => $admin['password'],
        ];
        unset(
            $_SESSION['install_db'],
            $_SESSION['install_admin'],
            $_SESSION['install_app'],
            $_SESSION['install_step']
        );

    } catch (\Throwable $ex) {
        return [t('step3.install_failed', e($ex->getMessage()))];
    }

    return [];
}

/**
 * View für Schritt 3 (Bestätigung).
 */
function renderStep3(): void
{
    $db    = $_SESSION['install_db']    ?? [];
    $admin = $_SESSION['install_admin'] ?? [];
    $app   = $_SESSION['install_app']   ?? [];
    ?>
    <h2 class="title is-5"><?= e(t('step3.heading')) ?></h2>
    <p class="mb-4 has-text-grey is-size-7"><?= e(t('step3.subheading')) ?></p>

    <div class="notification is-warning is-light warn-left mb-4">
        ⚠️ <?= e(t('step3.warning')) ?>
    </div>

    <?php /* ── Datenbank ── */ ?>
    <p class="section-title">🗄️ <?= e(t('step2.db_section')) ?></p>
    <table class="table is-fullwidth is-bordered is-size-7">
        <tbody>
        <tr><td><strong><?= e(t('step2.db_driver')) ?></strong></td><td><?= e($db['driver'] ?? '') ?></td></tr>
        <?php if (($db['driver'] ?? '') === 'pdo_sqlite'): ?>
        <tr><td><strong><?= e(t('step2.sqlite_path')) ?></strong></td><td><?= e($db['path'] ?? '') ?></td></tr>
        <?php elseif (($db['driver'] ?? '') === 'pdo_pgsql'): ?>
        <tr><td><strong><?= e(t('step2.hostname')) ?></strong></td><td><?= e($db['host'] ?? '') ?></td></tr>
        <tr><td><strong><?= e(t('step2.port')) ?></strong></td><td><?= e((string) ($db['port'] ?? 5432)) ?></td></tr>
        <tr><td><strong><?= e(t('step2.dbname')) ?></strong></td><td><?= e($db['name'] ?? '') ?></td></tr>
        <tr><td><strong><?= e(t('step2.dbuser')) ?></strong></td><td><?= e($db['user'] ?? '') ?></td></tr>
        <?php else: ?>
        <tr><td><strong><?= e(t('step2.hostname')) ?></strong></td><td><?= e($db['host'] ?? '') ?></td></tr>
        <tr><td><strong><?= e(t('step2.port')) ?></strong></td><td><?= e((string) ($db['port'] ?? 3306)) ?></td></tr>
        <tr><td><strong><?= e(t('step2.dbname')) ?></strong></td><td><?= e($db['name'] ?? '') ?></td></tr>
        <tr><td><strong><?= e(t('step2.dbuser')) ?></strong></td><td><?= e($db['user'] ?? '') ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php /* ── Admin ── */ ?>
    <p class="section-title">👤 <?= e(t('step2.admin_section')) ?></p>
    <table class="table is-fullwidth is-bordered is-size-7">
        <tbody>
        <tr><td><strong><?= e(t('step2.admin_username')) ?></strong></td><td><?= e($admin['username'] ?? '') ?></td></tr>
        <tr><td><strong><?= e(t('step2.admin_email')) ?></strong></td><td><?= e($admin['email'] ?? '') ?></td></tr>
        </tbody>
    </table>

    <?php /* ── App ── */ ?>
    <p class="section-title">⚙️ <?= e(t('step2.app_section')) ?></p>
    <table class="table is-fullwidth is-bordered is-size-7">
        <tbody>
        <tr><td><strong><?= e(t('step2.app_name')) ?></strong></td><td><?= e($app['name'] ?? '') ?></td></tr>
        <tr><td><strong><?= e(t('step2.app_domain')) ?></strong></td><td><?= e($app['domain'] ?? '') ?></td></tr>
        <tr><td><strong><?= e(t('step2.app_theme')) ?></strong></td><td><?= e($app['theme'] ?? '') ?></td></tr>
        <tr><td><strong>HTTPS</strong></td><td><?= ($app['https'] ?? false) ? '✓' : '✗' ?></td></tr>
        </tbody>
    </table>

    <div class="buttons mt-4">
        <form method="post" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">
            <input type="hidden" name="action" value="install">
            <button type="submit" class="button is-danger is-medium"
                    onclick="return confirm('<?= e(t('step3.confirm')) ?>')">
                🚀 <?= e(t('step3.btn_install')) ?>
            </button>
        </form>
        <a href="index.php?step=back" class="button is-light is-medium">← <?= e(t('nav.back')) ?></a>
    </div>
    <?php
}
