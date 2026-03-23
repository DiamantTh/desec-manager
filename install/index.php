<?php
/**
 * deSEC Manager – Web-Installer (install/index.php)
 *
 * SICHERHEITSHINWEIS: Diesen Ordner nach erfolgreicher Installation löschen!
 *   rm -rf install/
 *
 * Schritte:
 *   1. System-Check   – PHP-Version, Extensions, Composer vendor/
 *   2. Konfiguration  – Datenbank · Admin-Benutzer · Anwendungs-Einstellungen
 *   3. Bestätigung    – Zusammenfassung + Installation + Ergebnis
 */

declare(strict_types=1);

// ── Pfade ────────────────────────────────────────────────────────────────────
define('PROJECT_ROOT', dirname(__DIR__));
define('LOCK_FILE',    __DIR__ . '/.lock');
define('VENDOR_OK',    file_exists(PROJECT_ROOT . '/vendor/autoload.php'));

// ── Bereits installiert? (Lock-Datei) ────────────────────────────────────────
if (file_exists(LOCK_FILE)) {
    renderLocked();
    exit;
}

session_start();

// ── Neustart ─────────────────────────────────────────────────────────────────
if (isset($_GET['restart'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
define('CSRF_TOKEN', $_SESSION['install_csrf']);

// ── Schritt initialisieren ───────────────────────────────────────────────────
if (!isset($_SESSION['install_step'])) {
    $_SESSION['install_step'] = 1;
}
$step   = (int) $_SESSION['install_step'];
$errors = [];

// ── POST verarbeiten ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(CSRF_TOKEN, (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        die('CSRF-Validierung fehlgeschlagen.');
    }

    $errors = match ($step) {
        1 => processStep1(),
        2 => processStep2(),
        3 => processStep3(),
        default => ['Ungültiger Schritt.'],
    };

    if (empty($errors)) {
        $step = $_SESSION['install_step'];   // processStepN() setzt dies selbst
    }
}

// ─────────────────────────────────────────────────────────────────────────────
//  Schritt-Handler
// ─────────────────────────────────────────────────────────────────────────────

/** @return string[] */
function processStep1(): array
{
    if (!VENDOR_OK) {
        return ['vendor/ fehlt. Bitte zuerst <code>composer install --no-dev</code> ausführen.'];
    }

    $reqs = getRequirements();
    foreach ($reqs as $key => $req) {
        if ($key === 'already_installed') {
            continue;   // kein Blocker
        }
        if (!$req['ok']) {
            return ['Systemanforderungen nicht erfüllt. Bitte die markierten Punkte beheben.'];
        }
    }

    $_SESSION['install_step'] = 2;
    return [];
}

/** @return string[] */
function processStep2(): array
{
    $errors = [];

    // ── Datenbank ─────────────────────────────────────────────────────────────
    $driver = (string) ($_POST['db_driver'] ?? 'pdo_mysql');
    if (!in_array($driver, ['pdo_mysql', 'pdo_sqlite'], true)) {
        $errors[] = 'Ungültiger Datenbank-Treiber.';
        return $errors;
    }

    if ($driver === 'pdo_sqlite') {
        $path = trim((string) ($_POST['db_sqlite_path'] ?? ''));
        if ($path === '') {
            $path = PROJECT_ROOT . '/var/database.sqlite';
        }
        // Pfad-Sicherheit: kein Null-Byte, vernünftige Länge
        if (str_contains($path, "\0") || strlen($path) > 500) {
            $errors[] = 'Ungültiger SQLite-Pfad.';
            return $errors;
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            $errors[] = "Verzeichnis konnte nicht erstellt werden: " . e($dir);
            return $errors;
        }
        try {
            new PDO('sqlite:' . $path);
        } catch (\Exception $ex) {
            $errors[] = 'SQLite-Verbindungsfehler: ' . e($ex->getMessage());
            return $errors;
        }
        $_SESSION['install_db'] = ['driver' => 'pdo_sqlite', 'path' => $path];
    } else {
        $host   = trim((string) ($_POST['db_host'] ?? 'localhost'));
        $port   = (int) ($_POST['db_port'] ?? 3306);
        $name   = trim((string) ($_POST['db_name'] ?? ''));
        $user   = trim((string) ($_POST['db_user'] ?? ''));
        $pass   = (string) ($_POST['db_pass'] ?? '');
        $create = !empty($_POST['db_create']);

        // Eingabevalidierung
        if ($host === '' || strlen($host) > 255) {
            $errors[] = 'Hostname ungültig (1–255 Zeichen).';
        }
        if ($port < 1 || $port > 65535) {
            $errors[] = 'Port muss zwischen 1 und 65535 liegen.';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name) || strlen($name) > 64) {
            $errors[] = 'Datenbankname darf nur Buchstaben, Ziffern und _ enthalten (max. 64 Zeichen).';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $user) || strlen($user) > 32) {
            $errors[] = 'Datenbankbenutzer darf nur Buchstaben, Ziffern und _ enthalten (max. 32 Zeichen).';
        }
        if (!empty($errors)) {
            return $errors;
        }

        if ($create) {
            $rootUser = trim((string) ($_POST['db_root_user'] ?? 'root'));
            $rootPass = (string) ($_POST['db_root_pass'] ?? '');
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $rootUser) || strlen($rootUser) > 32) {
                $errors[] = 'Root-Benutzername ungültig.';
                return $errors;
            }
            try {
                $rootPdo = new PDO(
                    "mysql:host={$host};port={$port};charset=utf8mb4",
                    $rootUser, $rootPass,
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                );
                // Backtick-sichere Werte (durch Regex bereits validiert)
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $rootPdo->exec("CREATE USER IF NOT EXISTS '{$user}'@'%' IDENTIFIED BY " . $rootPdo->quote($pass));
                $rootPdo->exec("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$user}'@'%'");
                $rootPdo->exec("FLUSH PRIVILEGES");
            } catch (\Exception $ex) {
                $errors[] = 'Root-Verbindungsfehler: ' . e($ex->getMessage());
                return $errors;
            }
        }

        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user, $pass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            unset($pdo);
        } catch (\Exception $ex) {
            $errors[] = 'Datenbankverbindung fehlgeschlagen: ' . e($ex->getMessage());
            return $errors;
        }

        $_SESSION['install_db'] = [
            'driver' => 'pdo_mysql',
            'host'   => $host,
            'port'   => $port,
            'name'   => $name,
            'user'   => $user,
            'pass'   => $pass,
        ];
    }

    // ── Admin-Benutzer ────────────────────────────────────────────────────────
    $adminUser  = trim((string) ($_POST['admin_user'] ?? ''));
    $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
    $adminPass  = (string) ($_POST['admin_pass'] ?? '');
    $adminPass2 = (string) ($_POST['admin_pass2'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_\-\.]{3,50}$/', $adminUser)) {
        $errors[] = 'Benutzername: 3–50 Zeichen, nur Buchstaben, Ziffern, _, -, .';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminEmail) > 254) {
        $errors[] = 'Ungültige E-Mail-Adresse.';
    }
    if (strlen($adminPass) < 12) {
        $errors[] = 'Passwort muss mindestens 12 Zeichen lang sein.';
    }
    if ($adminPass !== $adminPass2) {
        $errors[] = 'Passwörter stimmen nicht überein.';
    }
    if (!empty($errors)) {
        return $errors;
    }

    $_SESSION['install_admin'] = [
        'username' => $adminUser,
        'email'    => $adminEmail,
        'password' => $adminPass,
    ];

    // ── Anwendungs-Einstellungen ──────────────────────────────────────────────
    $appName   = trim((string) ($_POST['app_name'] ?? 'DeSEC Manager'));
    $appDomain = trim((string) ($_POST['app_domain'] ?? ''));
    $appTheme  = (string) ($_POST['app_theme'] ?? 'default');
    $appHttps  = !empty($_POST['app_https']);

    $appName = htmlspecialchars(strip_tags($appName), ENT_QUOTES, 'UTF-8');
    if ($appName === '' || strlen($appName) > 100) {
        $errors[] = 'App-Name muss 1–100 Zeichen lang sein.';
    }
    if ($appDomain !== '' && !preg_match('/^[a-zA-Z0-9.\-]{1,253}$/', $appDomain)) {
        $errors[] = 'Hostname ungültig (nur alphanumerisch, Punkte, Bindestriche).';
    }

    // Theme-Whitelist
    $allowedThemes = getAvailableThemes();
    if (!array_key_exists($appTheme, $allowedThemes)) {
        $appTheme = 'default';
    }

    if (!empty($errors)) {
        return $errors;
    }

    $_SESSION['install_app'] = [
        'domain' => $appDomain,
        'name'   => $appName,
        'theme'  => $appTheme,
        'https'  => $appHttps,
    ];

    $_SESSION['install_step'] = 3;
    return [];
}

/** @return string[] */
function processStep3(): array
{
    if (!VENDOR_OK) {
        return ['vendor/ fehlt. Bitte zuerst <code>composer install --no-dev</code> ausführen.'];
    }

    require_once PROJECT_ROOT . '/vendor/autoload.php';

    $db    = $_SESSION['install_db']    ?? [];
    $admin = $_SESSION['install_admin'] ?? [];
    $app   = $_SESSION['install_app']   ?? [];

    if (empty($db) || empty($admin) || empty($app)) {
        return ['Sitzungsdaten unvollständig. Bitte von vorne beginnen.'];
    }

    try {
        $encKey = function_exists('sodium_crypto_secretbox_keygen')
            ? base64_encode(sodium_crypto_secretbox_keygen())
            : base64_encode(random_bytes(32));

        // Doctrine-DBAL Verbindungsparameter
        $params = $db['driver'] === 'pdo_sqlite'
            ? ['driver' => 'pdo_sqlite', 'path' => $db['path']]
            : [
                'driver'  => 'pdo_mysql',
                'host'    => $db['host'],
                'port'    => (int) $db['port'],
                'dbname'  => $db['name'],
                'user'    => $db['user'],
                'password'=> $db['pass'],
                'charset' => 'utf8mb4',
            ];

        $conn   = \Doctrine\DBAL\DriverManager::getConnection($params);
        $schema = new \Doctrine\DBAL\Schema\Schema();

        // Tabellen anlegen
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
        ] as [$col, $type, $opts]) {
            $t->addColumn($col, $type, $opts);
        }
        $t->setPrimaryKey(['id']);
        $t->addUniqueIndex(['username']);
        $t->addUniqueIndex(['email']);

        $k = $schema->createTable('api_keys');
        foreach ([
            ['id',         'integer', ['autoincrement' => true]],
            ['user_id',    'integer', []],
            ['name',       'string',  ['length' => 255]],
            ['api_key',    'string',  ['length' => 255]],
            ['created_at', 'string',  ['length' => 32, 'notnull' => false]],
            ['last_used',  'string',  ['length' => 32, 'notnull' => false]],
            ['is_active',  'boolean', ['default' => true]],
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

        foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
            $conn->executeStatement($sql);
        }

        // Admin-Benutzer anlegen
        $hash = password_hash(
            $admin['password'],
            PASSWORD_ARGON2ID,
            ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]
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

        // Konfigurationsdatei schreiben
        $dbSection = ['driver' => $db['driver'], 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'];
        if ($db['driver'] === 'pdo_sqlite') {
            $dbSection['path'] = $db['path'];
        } else {
            $dbSection += [
                'host' => $db['host'],
                'port' => (int) $db['port'],
                'name' => $db['name'],
                'user' => $db['user'],
                'pass' => $db['pass'],
            ];
        }

        $cfg = [
            'database'    => $dbSection,
            'security'    => [
                'algo'           => PASSWORD_ARGON2ID,
                'options'        => ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2],
                'encryption_key' => $encKey,
            ],
            'application' => [
                'domain'           => $app['domain'],
                'name'             => $app['name'],
                'webauthn_enabled' => true,
                'force_https'      => (bool) $app['https'],
            ],
            'theme' => ['name' => $app['theme']],
            'debug' => false,
        ];

        $cfgDir  = PROJECT_ROOT . '/config';
        $cfgFile = $cfgDir . '/config.php';
        if (!is_dir($cfgDir)) {
            mkdir($cfgDir, 0750, true);
        }
        if (file_exists($cfgFile)) {
            copy($cfgFile, $cfgFile . '.bak.' . date('Y-m-d-H-i-s'));
        }
        file_put_contents($cfgFile, "<?php\n// Auto-generiert am " . date('Y-m-d H:i:s') . "\nreturn " . var_export($cfg, true) . ";\n");
        chmod($cfgFile, 0600);

        // Lock-Datei schreiben
        file_put_contents(LOCK_FILE, date('Y-m-d H:i:s') . "\n");

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
        return ['Installation fehlgeschlagen: ' . e($ex->getMessage())];
    }

    return [];
}

// ─────────────────────────────────────────────────────────────────────────────
//  Hilfsfunktionen
// ─────────────────────────────────────────────────────────────────────────────

/** @return array<string, array{ok: bool, label: string, detail: string, critical: bool}> */
function getRequirements(): array
{
    $c = [];

    $phpOk = version_compare(PHP_VERSION, '8.1.0') >= 0;
    $c['php'] = ['ok' => $phpOk, 'label' => 'PHP ≥ 8.1', 'detail' => 'Installiert: ' . PHP_VERSION, 'critical' => true];

    foreach (['pdo', 'sodium', 'openssl', 'json', 'mbstring'] as $ext) {
        $ok = extension_loaded($ext);
        $c[$ext] = ['ok' => $ok, 'label' => "PHP-Erweiterung: {$ext}", 'detail' => $ok ? 'Geladen ✓' : 'FEHLT', 'critical' => true];
    }

    $hasMysql  = extension_loaded('pdo_mysql');
    $hasSqlite = extension_loaded('pdo_sqlite');
    $c['pdo_db'] = [
        'ok'       => $hasMysql || $hasSqlite,
        'label'    => 'Datenbank-Treiber',
        'detail'   => 'pdo_mysql: ' . ($hasMysql ? '✓' : '✗') . ' | pdo_sqlite: ' . ($hasSqlite ? '✓' : '✗'),
        'critical' => true,
    ];

    $c['vendor'] = [
        'ok'       => VENDOR_OK,
        'label'    => 'Composer vendor/',
        'detail'   => VENDOR_OK ? 'Vorhanden ✓' : 'FEHLT – bitte <code>composer install --no-dev</code> ausführen',
        'critical' => true,
    ];

    $configDir = PROJECT_ROOT . '/config';
    $writable  = is_dir($configDir) ? is_writable($configDir) : is_writable(PROJECT_ROOT);
    $c['config_writable'] = [
        'ok'       => $writable,
        'label'    => 'Schreibrecht config/',
        'detail'   => $writable ? 'OK ✓' : 'Kein Schreibrecht',
        'critical' => true,
    ];

    $already = file_exists(PROJECT_ROOT . '/config/config.php');
    $c['already_installed'] = [
        'ok'       => true,
        'label'    => 'Vorherige Installation',
        'detail'   => $already ? '⚠️ config.php existiert – Reinstallation erstellt Backup' : 'Keine vorherige Installation',
        'critical' => false,
    ];

    return $c;
}

/** @return array<string, string> */
function getAvailableThemes(): array
{
    $themes = ['default' => 'Default', 'bulma' => 'Bulma Classic'];
    $td = PROJECT_ROOT . '/themes';
    if (is_dir($td)) {
        foreach ((array) scandir($td) as $entry) {
            if (is_string($entry) && $entry[0] !== '.' && is_dir($td . '/' . $entry) && !isset($themes[$entry])) {
                $jf = $td . '/' . $entry . '/theme.json';
                $themes[$entry] = file_exists($jf)
                    ? (json_decode((string) file_get_contents($jf), true)['name'] ?? $entry)
                    : $entry;
            }
        }
    }
    return $themes;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─────────────────────────────────────────────────────────────────────────────
//  Spezial-Seiten: Bereits installiert / Gesperrt
// ─────────────────────────────────────────────────────────────────────────────
function renderLocked(): void
{
    http_response_code(403);
    ?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Installer gesperrt — DeSEC Manager</title>
    <link rel="stylesheet" href="../assets/css/bulma.min.css">
</head>
<body style="background:#f5f5f5">
<section class="section">
    <div class="container" style="max-width:640px">
        <div class="notification is-danger">
            <strong>🔒 Installer gesperrt.</strong><br>
            Der DeSEC Manager wurde bereits installiert. Der Installer ist daher deaktiviert.<br><br>
            <strong>Empfehlung:</strong> Löschen Sie den gesamten <code>install/</code>-Ordner:<br>
            <pre style="background:#fff;padding:.5rem;border-radius:4px;margin-top:.5rem"><code>rm -rf install/</code></pre>
        </div>
        <a href="../index.php" class="button is-primary">→ Zur Anwendung</a>
    </div>
</section>
</body>
</html>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
//  Render-Hilfsdaten
// ─────────────────────────────────────────────────────────────────────────────
$reqs   = getRequirements();
$themes = getAvailableThemes();

$allCriticalOk = true;
foreach ($reqs as $key => $req) {
    if ($req['critical'] && !$req['ok']) {
        $allCriticalOk = false;
        break;
    }
}

// Schritt-Labels (Komponenten-basiert, nicht "Schritt N von 5")
$stepLabels = ['System-Check', 'Konfiguration', 'Bestätigung'];
$isSuccess  = isset($_SESSION['install_result']);

// Aktuellen Schritt für die Anzeige ermitteln
$displayStep = $isSuccess ? 4 : $step;   // 4 = Erfolgs-Anzeige

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>DeSEC Manager — Installation</title>
    <link rel="stylesheet" href="../assets/css/bulma.min.css">
    <style>
        body { background: #f0f7ff; }
        .wizard-card { max-width: 820px; margin: 2rem auto; }
        .progress-bar { display: flex; gap: .5rem; margin-bottom: 2rem; }
        .progress-bar .pb-step {
            flex: 1; text-align: center; padding: .45rem .3rem;
            border-radius: 8px; font-size: .8rem; font-weight: 700;
            background: #e2e8f0; color: #64748b;
        }
        .progress-bar .pb-step.done   { background: #4caf50; color: #fff; }
        .progress-bar .pb-step.active { background: #2196f3; color: #fff; }
        .section-divider { border: none; border-top: 2px solid #e2e8f0; margin: 1.5rem 0; }
        .section-title { font-size: 1rem; font-weight: 700; color: #2196f3; margin-bottom: 1rem; }
        code { background: #f1f5f9; padding: .1em .3em; border-radius: 4px; font-size: .875em; }
        pre  { background: #f8fafc; border-radius: 6px; padding: .75rem 1rem; font-size: .85rem; }
        .warn-left { border-left: 4px solid #ff9800; padding-left: 1rem; }
        #mysql_fields, #sqlite_fields, #root_fields { transition: none; }
    </style>
</head>
<body>
<div class="wizard-card card">
    <header class="card-header" style="background:linear-gradient(135deg,#1565c0,#0d47a1);border-radius:9px 9px 0 0">
        <p class="card-header-title" style="color:#fff;font-size:1.1rem">🛠️ DeSEC Manager — Installation</p>
    </header>
    <div class="card-content">

        <?php if (!$isSuccess): ?>
        <div class="progress-bar">
            <?php foreach ($stepLabels as $i => $lbl):
                $n = $i + 1;
                $cls = $n < $displayStep ? 'done' : ($n === $displayStep ? 'active' : '');
                $icon = $n < $displayStep ? '✓ ' : '';
            ?>
            <div class="pb-step <?= $cls ?>"><?= $icon . e($lbl) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="notification is-danger is-light mb-4">
            <ul>
                <?php foreach ($errors as $err): ?>
                <li><?= $err ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php /* ══════════════════════════════════════════════════════════════
               SCHRITT 1: SYSTEM-CHECK
               ══════════════════════════════════════════════════════════════ */
        if ($step === 1 && !$isSuccess): ?>

        <h2 class="title is-5">System-Check</h2>
        <p class="mb-4 has-text-grey is-size-7">Alle <strong>kritischen</strong> Anforderungen müssen erfüllt sein, bevor Sie fortfahren können.</p>

        <table class="table is-fullwidth is-striped is-hoverable">
            <thead>
                <tr><th>Prüfung</th><th>Status</th><th>Details</th></tr>
            </thead>
            <tbody>
            <?php foreach ($reqs as $req): ?>
            <tr>
                <td>
                    <?= e($req['label']) ?>
                    <?php if ($req['critical']): ?>
                    <span class="tag is-light is-size-7 ml-1">Erforderlich</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($req['ok']): ?>
                    <span class="tag is-success">OK</span>
                    <?php elseif ($req['critical']): ?>
                    <span class="tag is-danger">FEHLT</span>
                    <?php else: ?>
                    <span class="tag is-warning">Hinweis</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.85rem"><?= $req['detail'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!VENDOR_OK): ?>
        <div class="notification is-warning warn-left mb-4">
            <strong>Composer-Abhängigkeiten fehlen.</strong> Bitte ausführen:<br>
            <pre><code>composer install --no-dev</code></pre>
            Danach diese Seite neu laden.
        </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">
            <button type="submit" class="button is-primary" <?= !$allCriticalOk ? 'disabled title="Bitte zuerst alle Anforderungen erfüllen."' : '' ?>>
                Weiter → Konfiguration
            </button>
        </form>

        <?php /* ══════════════════════════════════════════════════════════════
               SCHRITT 2: KONFIGURATION (DB + Admin + App in einer Seite)
               ══════════════════════════════════════════════════════════════ */
        elseif ($step === 2 && !$isSuccess): ?>

        <h2 class="title is-5">Konfiguration</h2>
        <p class="mb-4 has-text-grey is-size-7">Füllen Sie alle drei Abschnitte aus und klicken Sie auf <strong>„Verbinden &amp; weiter"</strong>.</p>

        <form method="post" id="config-form">
            <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">

            <?php /* ── Abschnitt A: Datenbank ── */ ?>
            <p class="section-title">🗄️ Datenbank</p>

            <div class="field">
                <label class="label">Datenbank-Treiber</label>
                <div class="control"><div class="select">
                    <select name="db_driver" id="db_driver" onchange="toggleDb()">
                        <option value="pdo_mysql" <?= !extension_loaded('pdo_mysql') ? 'disabled' : '' ?>>MySQL / MariaDB</option>
                        <option value="pdo_sqlite" <?= !extension_loaded('pdo_sqlite') ? 'disabled' : '' ?>>SQLite</option>
                    </select>
                </div></div>
            </div>

            <div id="mysql_fields">
                <div class="columns">
                    <div class="column is-three-quarters">
                        <div class="field">
                            <label class="label">Hostname</label>
                            <div class="control"><input class="input" type="text" name="db_host" value="localhost" maxlength="255"></div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field">
                            <label class="label">Port</label>
                            <div class="control"><input class="input" type="number" name="db_port" value="3306" min="1" max="65535"></div>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Datenbankname <span class="has-text-grey is-size-7">(a-z, 0-9, _)</span></label>
                    <div class="control"><input class="input" type="text" name="db_name" value="desec_manager" pattern="[a-zA-Z0-9_]+" maxlength="64"></div>
                </div>
                <div class="field">
                    <label class="label">Benutzer <span class="has-text-grey is-size-7">(a-z, 0-9, _)</span></label>
                    <div class="control"><input class="input" type="text" name="db_user" value="desec_user" pattern="[a-zA-Z0-9_]+" maxlength="32"></div>
                </div>
                <div class="field">
                    <label class="label">Passwort</label>
                    <div class="control"><input class="input" type="password" name="db_pass" autocomplete="new-password"></div>
                </div>
                <div class="field">
                    <div class="control">
                        <label class="checkbox">
                            <input type="checkbox" name="db_create" id="db_create" onchange="toggleRoot()">
                            Datenbank + Benutzer automatisch anlegen (Root-Zugang erforderlich)
                        </label>
                    </div>
                </div>
                <div id="root_fields" style="display:none">
                    <div class="notification is-info is-light is-size-7">Root-Credentials werden nicht gespeichert und nur für die DB-Anlage verwendet.</div>
                    <div class="field">
                        <label class="label">Root-Benutzer</label>
                        <div class="control"><input class="input" type="text" name="db_root_user" value="root" pattern="[a-zA-Z0-9_]+" maxlength="32"></div>
                    </div>
                    <div class="field">
                        <label class="label">Root-Passwort</label>
                        <div class="control"><input class="input" type="password" name="db_root_pass" autocomplete="off"></div>
                    </div>
                </div>
            </div>

            <div id="sqlite_fields" style="display:none">
                <div class="field">
                    <label class="label">SQLite-Dateipfad</label>
                    <div class="control">
                        <input class="input" type="text" name="db_sqlite_path" placeholder="<?= e(PROJECT_ROOT . '/var/database.sqlite') ?>" maxlength="500">
                    </div>
                    <p class="help">Leer = Standard-Pfad. SQLite nur für Entwicklung / Einzelnutzer.</p>
                </div>
            </div>

            <hr class="section-divider">

            <?php /* ── Abschnitt B: Admin-Benutzer ── */ ?>
            <p class="section-title">👤 Admin-Benutzer</p>

            <div class="columns">
                <div class="column">
                    <div class="field">
                        <label class="label">Benutzername <span class="has-text-grey is-size-7">(3–50 Zeichen, a-z, 0-9, _, -, .)</span></label>
                        <div class="control"><input class="input" type="text" name="admin_user" value="admin" required pattern="[a-zA-Z0-9_\-\.]{3,50}" minlength="3" maxlength="50"></div>
                    </div>
                </div>
                <div class="column">
                    <div class="field">
                        <label class="label">E-Mail</label>
                        <div class="control"><input class="input" type="email" name="admin_email" required maxlength="254"></div>
                    </div>
                </div>
            </div>
            <div class="columns">
                <div class="column">
                    <div class="field">
                        <label class="label">Passwort <span class="tag is-light is-size-7">min. 12 Zeichen</span></label>
                        <div class="control"><input class="input" type="password" name="admin_pass" required minlength="12" autocomplete="new-password" id="admin_pass"></div>
                    </div>
                </div>
                <div class="column">
                    <div class="field">
                        <label class="label">Passwort bestätigen</label>
                        <div class="control"><input class="input" type="password" name="admin_pass2" required minlength="12" autocomplete="new-password" id="admin_pass2"></div>
                    </div>
                </div>
            </div>
            <p id="pass-mismatch" class="help is-danger" style="display:none">⚠️ Passwörter stimmen nicht überein.</p>

            <hr class="section-divider">

            <?php /* ── Abschnitt C: Anwendungs-Einstellungen ── */ ?>
            <p class="section-title">⚙️ Anwendungs-Einstellungen</p>

            <div class="columns">
                <div class="column">
                    <div class="field">
                        <label class="label">App-Name</label>
                        <div class="control"><input class="input" type="text" name="app_name" value="DeSEC Manager" required maxlength="100"></div>
                    </div>
                </div>
                <div class="column">
                    <div class="field">
                        <label class="label">Hostname <span class="help is-inline ml-2">ohne https://</span></label>
                        <div class="control"><input class="input" type="text" name="app_domain" placeholder="manager.example.com" maxlength="253" pattern="[a-zA-Z0-9.\-]*"></div>
                        <p class="help">Für WebAuthn / Passkeys benötigt. Kann leer bleiben.</p>
                    </div>
                </div>
            </div>
            <div class="columns">
                <div class="column is-half">
                    <div class="field">
                        <label class="label">Theme</label>
                        <div class="control"><div class="select is-fullwidth">
                            <select name="app_theme">
                                <?php foreach ($themes as $v => $l): ?>
                                <option value="<?= e($v) ?>" <?= $v === 'default' ? 'selected' : '' ?>><?= e($l) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div></div>
                    </div>
                </div>
                <div class="column is-half">
                    <div class="field" style="padding-top:2rem">
                        <div class="control">
                            <label class="checkbox"><input type="checkbox" name="app_https" checked> HTTPS erzwingen</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="field mt-5">
                <div class="control">
                    <button type="submit" class="button is-primary is-medium" id="submit-btn">
                        Verbinden &amp; weiter → Bestätigung
                    </button>
                </div>
            </div>
        </form>

        <script>
        function toggleDb() {
            var v = document.getElementById('db_driver').value;
            document.getElementById('mysql_fields').style.display  = v === 'pdo_mysql'  ? '' : 'none';
            document.getElementById('sqlite_fields').style.display = v === 'pdo_sqlite' ? '' : 'none';
        }
        function toggleRoot() {
            document.getElementById('root_fields').style.display =
                document.getElementById('db_create').checked ? '' : 'none';
        }
        // Passwort-Match-Prüfung
        ['admin_pass','admin_pass2'].forEach(function(id) {
            document.getElementById(id).addEventListener('input', function() {
                var p1 = document.getElementById('admin_pass').value;
                var p2 = document.getElementById('admin_pass2').value;
                var msg = document.getElementById('pass-mismatch');
                msg.style.display = (p1 && p2 && p1 !== p2) ? '' : 'none';
            });
        });
        </script>

        <?php /* ══════════════════════════════════════════════════════════════
               SCHRITT 3: BESTÄTIGUNG
               ══════════════════════════════════════════════════════════════ */
        elseif ($step === 3 && !$isSuccess): ?>

        <?php
        $db    = $_SESSION['install_db']    ?? [];
        $admin = $_SESSION['install_admin'] ?? [];
        $app   = $_SESSION['install_app']   ?? [];
        ?>

        <h2 class="title is-5">Bestätigung &amp; Installation</h2>
        <p class="mb-4 has-text-grey is-size-7">Bitte prüfen Sie die Zusammenfassung und starten Sie dann die Installation.</p>

        <div class="box">
            <table class="table is-fullwidth is-narrow is-borderless">
                <tbody>
                <tr>
                    <th width="35%">Datenbank</th>
                    <td>
                        <?php if (($db['driver'] ?? '') === 'pdo_sqlite'): ?>
                            SQLite: <code><?= e($db['path'] ?? '') ?></code>
                        <?php else: ?>
                            MySQL: <code><?= e(($db['host'] ?? '') . ':' . ($db['port'] ?? 3306) . '/' . ($db['name'] ?? '')) ?></code>
                            (Benutzer: <code><?= e($db['user'] ?? '') ?></code>)
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Admin-Benutzer</th>
                    <td><?= e($admin['username'] ?? '') ?> &lt;<?= e($admin['email'] ?? '') ?>&gt;</td>
                </tr>
                <tr>
                    <th>App-Name</th>
                    <td><?= e($app['name'] ?? '') ?></td>
                </tr>
                <tr>
                    <th>Hostname</th>
                    <td><?= $app['domain'] !== '' ? e($app['domain'] ?? '') : '<span class="has-text-grey">(leer)</span>' ?></td>
                </tr>
                <tr>
                    <th>Theme</th>
                    <td><?= e($app['theme'] ?? 'default') ?></td>
                </tr>
                <tr>
                    <th>HTTPS erzwingen</th>
                    <td><?= !empty($app['https']) ? '✓ Ja' : '✗ Nein' ?></td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="notification is-warning warn-left mb-4">
            <strong>⚠️ Nach der Installation:</strong> Den <code>install/</code>-Ordner löschen:<br>
            <pre><code>rm -rf install/</code></pre>
        </div>

        <div class="buttons">
            <form method="post" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">
                <button type="submit" class="button is-success is-medium">✓ Jetzt installieren</button>
            </form>
            <a href="?restart=1" class="button is-light is-medium" onclick="return confirm('Alle Eingaben verwerfen und neu starten?')">↺ Neu starten</a>
        </div>

        <?php /* ══════════════════════════════════════════════════════════════
               ERFOLG
               ══════════════════════════════════════════════════════════════ */
        elseif ($isSuccess): ?>

        <?php $result = $_SESSION['install_result'] ?? []; unset($_SESSION['install_result']); ?>

        <div class="notification is-success">
            <strong>✅ Installation erfolgreich abgeschlossen!</strong>
        </div>

        <div class="box" style="border: 2px solid #4caf50">
            <p class="mb-2"><strong>Admin-Benutzername:</strong> <code><?= e($result['admin_user'] ?? '') ?></code></p>
            <p><strong>Admin-Passwort:</strong>
                <code style="background:#e8f5e9;padding:.3em .6em;border-radius:4px"><?= e($result['admin_pass'] ?? '') ?></code>
            </p>
            <p class="mt-3 is-size-7 has-text-danger">⚠️ Dieses Passwort wird nicht erneut angezeigt. Bitte sofort an einem sicheren Ort sichern!</p>
        </div>

        <div class="notification is-danger is-light mt-3 warn-left">
            <strong>Sicherheitshinweis:</strong> Löschen Sie jetzt den <code>install/</code>-Ordner:<br>
            <pre><code>rm -rf install/</code></pre>
            Der Installer ist bereits durch eine Lock-Datei gesperrt, aber das Löschen ist die sicherste Option.
        </div>

        <a href="../index.php" class="button is-primary mt-2">→ Zur Anwendung</a>

        <?php endif; ?>

    </div>
</div>

</body>
</html>
