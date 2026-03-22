<?php
/**
 * deSEC Manager – Installer
 *
 * Erkennt automatisch CLI- vs. Web-Modus:
 *   - Im Browser: Mehrstufiger Web-Wizard (Schritte 1–5)
 *   - Auf der CLI: Interaktives Konsolenprogramm (install-cli.php)
 *
 * SICHERHEITSHINWEIS: Diese Datei nach der Installation löschen oder per
 * Webserver-Konfiguration sperren!
 */

declare(strict_types=1);

// ─── Modus erkennen ──────────────────────────────────────────────────────────
if (PHP_SAPI === 'cli') {
    require __DIR__ . '/install-cli.php';
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════════
//  WEB-WIZARD
// ═══════════════════════════════════════════════════════════════════════════════

session_start();

// CSRF
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

define('CSRF_TOKEN', $_SESSION['install_csrf']);
define('PROJECT_ROOT', __DIR__);
define('VENDOR_OK', file_exists(__DIR__ . '/vendor/autoload.php'));

// ── Schritt-Verwaltung ───────────────────────────────────────────────────────
$step   = (int) ($_SESSION['install_step'] ?? 1);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF prüfen
    if (!hash_equals(CSRF_TOKEN, (string) ($_POST['_csrf'] ?? ''))) {
        die('CSRF-Validierung fehlgeschlagen.');
    }

    switch ($step) {
        case 1: $errors = processStep1(); break;
        case 2: $errors = processStep2(); break;
        case 3: $errors = processStep3(); break;
        case 4: $errors = processStep4(); break;
        case 5: $errors = processStep5(); break;
    }

    if (empty($errors)) {
        $step = ++$_SESSION['install_step'];
    }
}

if (!isset($_SESSION['install_step'])) {
    $_SESSION['install_step'] = 1;
    $step = 1;
}

// ─── Schritt-Verarbeitung ────────────────────────────────────────────────────

/** @return string[] */
function processStep1(): array
{
    $_SESSION['install_step'] = 2;
    return [];
}

/** @return string[] */
function processStep2(): array
{
    $errors = [];

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
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            $errors[] = "Verzeichnis konnte nicht erstellt werden: {$dir}";
            return $errors;
        }
        try {
            new PDO('sqlite:' . $path);
        } catch (\Exception $e) {
            $errors[] = 'SQLite-Verbindungsfehler: ' . $e->getMessage();
            return $errors;
        }
        $_SESSION['install_db'] = ['driver' => 'pdo_sqlite', 'path' => $path];
        return [];
    }

    // MySQL / MariaDB
    $host   = trim((string) ($_POST['db_host'] ?? 'localhost'));
    $port   = (int) ($_POST['db_port'] ?? 3306);
    $name   = trim((string) ($_POST['db_name'] ?? ''));
    $user   = trim((string) ($_POST['db_user'] ?? ''));
    $pass   = (string) ($_POST['db_pass'] ?? '');
    $create = !empty($_POST['db_create']);

    if ($host === '') $errors[] = 'Hostname darf nicht leer sein.';
    if ($name === '') $errors[] = 'Datenbankname darf nicht leer sein.';
    if ($user === '') $errors[] = 'Datenbankbenutzer darf nicht leer sein.';
    if (!empty($errors)) return $errors;

    if ($create) {
        $rootUser = trim((string) ($_POST['db_root_user'] ?? 'root'));
        $rootPass = (string) ($_POST['db_root_pass'] ?? '');
        try {
            $rootPdo = new PDO("mysql:host={$host};port={$port}", $rootUser, $rootPass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $rootPdo->exec("CREATE USER IF NOT EXISTS '{$user}'@'localhost' IDENTIFIED BY '{$pass}'");
            $rootPdo->exec("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$user}'@'localhost'");
            $rootPdo->exec("FLUSH PRIVILEGES");
        } catch (\Exception $e) {
            $errors[] = 'Root-Verbindungsfehler: ' . $e->getMessage();
            return $errors;
        }
    }

    try {
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        unset($pdo);
    } catch (\Exception $e) {
        $errors[] = 'Datenbankverbindung fehlgeschlagen: ' . $e->getMessage();
        return $errors;
    }

    $_SESSION['install_db'] = [
        'driver' => 'pdo_mysql', 'host' => $host, 'port' => $port,
        'name' => $name, 'user' => $user, 'pass' => $pass,
    ];
    return [];
}

/** @return string[] */
function processStep3(): array
{
    $errors   = [];
    $username = trim((string) ($_POST['admin_user'] ?? 'admin'));
    $email    = trim((string) ($_POST['admin_email'] ?? ''));
    $password = (string) ($_POST['admin_pass'] ?? '');
    $password2= (string) ($_POST['admin_pass2'] ?? '');

    if ($username === '' || strlen($username) < 3) $errors[] = 'Benutzername muss mind. 3 Zeichen lang sein.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Ungültige E-Mail-Adresse.';
    if (strlen($password) < 12)                     $errors[] = 'Passwort muss mind. 12 Zeichen lang sein.';
    if ($password !== $password2)                   $errors[] = 'Passwörter stimmen nicht überein.';
    if (!empty($errors)) return $errors;

    $_SESSION['install_admin'] = ['username' => $username, 'email' => $email, 'password' => $password];
    return [];
}

/** @return string[] */
function processStep4(): array
{
    $domain  = trim((string) ($_POST['app_domain'] ?? ''));
    $name    = trim((string) ($_POST['app_name'] ?? 'DeSEC Manager'));
    $theme   = (string) ($_POST['app_theme'] ?? 'default');
    $https   = !empty($_POST['app_https']);

    $allowed = ['default', 'bulma'];
    $dir     = PROJECT_ROOT . '/themes';
    if (is_dir($dir)) {
        foreach ((array) scandir($dir) as $e) {
            if ($e[0] !== '.' && is_dir($dir . '/' . $e)) $allowed[] = $e;
        }
    }
    if (!in_array($theme, array_unique($allowed), true)) $theme = 'default';

    $_SESSION['install_app'] = ['domain' => $domain, 'name' => $name, 'theme' => $theme, 'https' => $https];
    return [];
}

/** @return string[] */
function processStep5(): array
{
    if (!VENDOR_OK) return ['vendor/ fehlt. Bitte zuerst <code>composer install</code> ausführen.'];

    require_once PROJECT_ROOT . '/vendor/autoload.php';

    $db    = $_SESSION['install_db']    ?? [];
    $admin = $_SESSION['install_admin'] ?? [];
    $app   = $_SESSION['install_app']   ?? [];

    if (empty($db) || empty($admin) || empty($app)) return ['Bitte alle vorherigen Schritte ausfüllen.'];

    try {
        $encKey = function_exists('sodium_crypto_secretbox_keygen')
            ? base64_encode(sodium_crypto_secretbox_keygen())
            : base64_encode(random_bytes(32));

        $params = $db['driver'] === 'pdo_sqlite'
            ? ['driver' => 'pdo_sqlite', 'path' => $db['path']]
            : ['driver' => 'pdo_mysql', 'host' => $db['host'], 'port' => (int)$db['port'],
               'dbname' => $db['name'], 'user' => $db['user'], 'password' => $db['pass'], 'charset' => 'utf8mb4'];

        $conn   = \Doctrine\DBAL\DriverManager::getConnection($params);
        $schema = new \Doctrine\DBAL\Schema\Schema();

        // users
        $t = $schema->createTable('users');
        foreach ([
            ['id','integer',['autoincrement'=>true]], ['username','string',['length'=>255]],
            ['password_hash','string',['length'=>255]], ['email','string',['length'=>255]],
            ['created_at','string',['length'=>32,'notnull'=>false]],
            ['last_login','string',['length'=>32,'notnull'=>false]],
            ['is_active','boolean',['default'=>true]], ['is_admin','boolean',['default'=>false]],
            ['totp_secret','string',['length'=>255,'notnull'=>false,'default'=>null]],
            ['totp_enabled','boolean',['default'=>false]],
            ['totp_algorithm','string',['length'=>16,'default'=>'sha256']],
            ['totp_digits','integer',['default'=>8]],
            ['enc_key_salt','string',['length'=>64,'notnull'=>false,'default'=>null]],
            ['enc_key_wrapped','text',['notnull'=>false,'default'=>null]],
        ] as [$col,$type,$opts]) { $t->addColumn($col, $type, $opts); }
        $t->setPrimaryKey(['id']);
        $t->addUniqueIndex(['username']);
        $t->addUniqueIndex(['email']);

        // api_keys
        $k = $schema->createTable('api_keys');
        foreach ([
            ['id','integer',['autoincrement'=>true]], ['user_id','integer',[]],
            ['name','string',['length'=>255]], ['api_key','string',['length'=>255]],
            ['created_at','string',['length'=>32,'notnull'=>false]],
            ['last_used','string',['length'=>32,'notnull'=>false]],
            ['is_active','boolean',['default'=>true]],
        ] as [$col,$type,$opts]) { $k->addColumn($col, $type, $opts); }
        $k->setPrimaryKey(['id']);
        $k->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete'=>'CASCADE']);

        // domains
        $d = $schema->createTable('domains');
        foreach ([
            ['id','integer',['autoincrement'=>true]], ['user_id','integer',[]],
            ['domain_name','string',['length'=>255]],
            ['created_at','string',['length'=>32,'notnull'=>false]],
        ] as [$col,$type,$opts]) { $d->addColumn($col, $type, $opts); }
        $d->setPrimaryKey(['id']);
        $d->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete'=>'CASCADE']);
        $d->addUniqueIndex(['domain_name']);

        // webauthn_credentials
        $w = $schema->createTable('webauthn_credentials');
        foreach ([
            ['id','integer',['autoincrement'=>true]],
            ['user_id','integer',[]],
            ['credential_id','string',['length'=>512]],
            ['name','string',['length'=>255]],
            ['public_key_cbor','text',[]],
            ['sign_count','integer',['default'=>0]],
            ['aaguid','string',['length'=>64,'notnull'=>false,'default'=>null]],
            ['is_active','boolean',['default'=>true]],
            ['created_at','string',['length'=>32,'notnull'=>false]],
            ['last_used','string',['length'=>32,'notnull'=>false,'default'=>null]],
        ] as [$col,$type,$opts]) { $w->addColumn($col, $type, $opts); }
        $w->setPrimaryKey(['id']);
        $w->addUniqueIndex(['credential_id']);
        $w->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete'=>'CASCADE']);

        foreach ($schema->toSql($conn->getDatabasePlatform()) as $sql) {
            $conn->executeStatement($sql);
        }

        $hash = password_hash($admin['password'], PASSWORD_ARGON2ID,
            ['memory_cost'=>65536,'time_cost'=>4,'threads'=>2]);
        $now  = (new \DateTime())->format('Y-m-d H:i:s');
        $conn->insert('users', [
            'username' => $admin['username'], 'password_hash' => $hash,
            'email' => $admin['email'], 'is_admin' => 1, 'is_active' => 1, 'created_at' => $now,
        ]);

        // Verschlüsselungsschlüssel für Admin-Account generieren (Keybase-Muster)
        $adminId    = (int) $conn->lastInsertId();
        $encSalt    = base64_encode(random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES));
        $saltRaw    = (string) base64_decode($encSalt, true);
        $wrapKey    = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $admin['password'],
            $saltRaw,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
        );
        $userKey    = sodium_crypto_secretbox_keygen();
        $nonce      = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encWrapped = base64_encode($nonce . sodium_crypto_secretbox($userKey, $nonce, $wrapKey));
        sodium_memzero($wrapKey);
        sodium_memzero($userKey);
        $conn->update(
            'users',
            ['enc_key_salt' => $encSalt, 'enc_key_wrapped' => $encWrapped],
            ['id' => $adminId],
        );

        // Config schreiben
        $dbSection = ['driver'=>$db['driver'],'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci'];
        if ($db['driver']==='pdo_sqlite') { $dbSection['path']=$db['path']; }
        else { $dbSection+=['host'=>$db['host'],'port'=>(int)$db['port'],'name'=>$db['name'],'user'=>$db['user'],'pass'=>$db['pass']]; }

        $cfg = [
            'database'   => $dbSection,
            'security'   => ['algo'=>PASSWORD_ARGON2ID,'options'=>['memory_cost'=>65536,'time_cost'=>4,'threads'=>2],'encryption_key'=>$encKey],
            'application'=> ['domain'=>$app['domain'],'name'=>$app['name'],'webauthn_enabled'=>true,'force_https'=>(bool)$app['https']],
            'theme'      => ['name'=>$app['theme']],
            'debug'      => false,
        ];

        $cfgDir  = PROJECT_ROOT . '/config';
        $cfgFile = $cfgDir . '/config.php';
        if (!is_dir($cfgDir)) mkdir($cfgDir, 0750, true);
        if (file_exists($cfgFile)) copy($cfgFile, $cfgFile . '.bak.' . date('Y-m-d-H-i-s'));
        file_put_contents($cfgFile, "<?php\n// Auto-generiert\nreturn " . var_export($cfg, true) . ";\n");
        chmod($cfgFile, 0600);

        $_SESSION['install_result'] = [
            'admin_user' => $admin['username'],
            'admin_pass' => $admin['password'],
        ];
        unset($_SESSION['install_db'], $_SESSION['install_admin'], $_SESSION['install_app']);

    } catch (\Throwable $e) {
        return ['Installation fehlgeschlagen: ' . $e->getMessage()];
    }
    return [];
}

// ─── Requirements ────────────────────────────────────────────────────────────

/** @return array<string, array{ok: bool, label: string, detail: string}> */
function getRequirements(): array
{
    $c = [];
    $phpOk = version_compare(PHP_VERSION,'8.1.0') >= 0;
    $c['php'] = ['ok'=>$phpOk,'label'=>'PHP >= 8.1','detail'=>'Installiert: '.PHP_VERSION];
    foreach (['pdo','sodium','openssl','json','mbstring'] as $ext) {
        $ok = extension_loaded($ext);
        $c[$ext] = ['ok'=>$ok,'label'=>"Erweiterung: {$ext}",'detail'=>$ok?'Geladen':'FEHLT'];
    }
    $hasMysql  = extension_loaded('pdo_mysql');
    $hasSqlite = extension_loaded('pdo_sqlite');
    $c['pdo_db'] = ['ok'=>$hasMysql||$hasSqlite,'label'=>'DB-Treiber (pdo_mysql oder pdo_sqlite)',
        'detail'=>"MySQL: ".($hasMysql?'✓':'✗')." | SQLite: ".($hasSqlite?'✓':'✗')];
    $c['vendor'] = ['ok'=>VENDOR_OK,'label'=>'Composer vendor/','detail'=>VENDOR_OK?'Vorhanden':'FEHLT – composer install ausführen'];
    $configDir = PROJECT_ROOT . '/config';
    $writable  = is_dir($configDir) ? is_writable($configDir) : is_writable(PROJECT_ROOT);
    $c['config_writable'] = ['ok'=>$writable,'label'=>'Schreibrecht config/','detail'=>$writable?'OK':'FEHLT'];
    $already = file_exists(PROJECT_ROOT . '/config/config.php');
    $c['fresh'] = ['ok'=>true,'label'=>'Bereits installiert?',
        'detail'=>$already?'⚠️ config.php existiert – Reinstallation erstellt Backup':'Noch nicht installiert'];
    return $c;
}

function e(string $s): string { return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

// ─── Variablen für Render ─────────────────────────────────────────────────────
$reqs = getRequirements();
$criticalOk = array_reduce(
    array_filter($reqs, fn(array $r):bool => strpos($r['label'],'vendor') === false),
    fn(bool $c, array $r):bool => $c && $r['ok'], true
);

$themes = ['default'=>'Default (Hellblau + Grün)','bulma'=>'Bulma Classic'];
$td = PROJECT_ROOT . '/themes';
if (is_dir($td)) {
    foreach ((array)scandir($td) as $e) {
        if ($e[0]!=='.' && is_dir($td.'/'.$e) && !isset($themes[$e])) {
            $jf = $td.'/'.$e.'/theme.json';
            $themes[$e] = file_exists($jf)
                ? (json_decode((string)file_get_contents($jf),true)['name'] ?? $e)
                : $e;
        }
    }
}

$steps = ['Anforderungen','Datenbank','Admin','App-Einstellungen','Bestätigung'];
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>deSEC Manager — Installation</title>
    <link rel="stylesheet" href="assets/css/bulma.min.css">
    <style>
        body{background:#f0f7ff}
        .wizard-card{max-width:740px;margin:2rem auto}
        .step-bar{display:flex;gap:.4rem;margin-bottom:2rem}
        .step-bar .step{flex:1;text-align:center;padding:.35rem .2rem;border-radius:6px;font-size:.78rem;font-weight:600;background:#e2e8f0;color:#64748b}
        .step-bar .step.done{background:#4caf50;color:#fff}
        .step-bar .step.active{background:#2196f3;color:#fff}
        code{background:#f1f5f9;padding:.1em .3em;border-radius:4px;font-size:.875em}
        .warn-left{border-left:4px solid #ff9800;padding-left:1rem}
    </style>
</head>
<body>
<div class="wizard-card card">
    <header class="card-header" style="background:linear-gradient(135deg,#2196f3,#1565c0);border-radius:9px 9px 0 0">
        <p class="card-header-title" style="color:#fff;font-size:1.1rem">🛠️ deSEC Manager — Installation</p>
    </header>
    <div class="card-content">

        <div class="step-bar">
            <?php foreach ($steps as $i=>$lbl): $n=$i+1; ?>
            <div class="step <?= $n<$step?'done':($n===$step?'active':'') ?>">
                <?= $n<$step?'✓':$n ?> <?= e($lbl) ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="notification is-danger is-light mb-4">
            <ul><?php foreach($errors as $err):?><li><?= $err ?></li><?php endforeach?></ul>
        </div>
        <?php endif ?>

        <?php if ($step===1): /* ── SCHRITT 1: ANFORDERUNGEN ─────────── */ ?>
        <h2 class="title is-5">Schritt 1: Systemanforderungen</h2>
        <table class="table is-fullwidth is-striped">
            <thead><tr><th>Prüfung</th><th>Status</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach($reqs as $req): ?>
            <tr>
                <td><?= e($req['label']) ?></td>
                <td><?= $req['ok']?'<span class="tag is-success">OK</span>':'<span class="tag is-danger">FEHLT</span>' ?></td>
                <td style="font-size:.85rem"><?= $req['detail'] ?></td>
            </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">
            <button type="submit" class="button is-primary" <?= !$criticalOk?'disabled':'' ?>>Weiter →</button>
        </form>

        <?php elseif ($step===2): /* ── SCHRITT 2: DATENBANK ─────────── */ ?>
        <h2 class="title is-5">Schritt 2: Datenbank</h2>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">
            <div class="field">
                <label class="label">Datenbank-Treiber</label>
                <div class="control"><div class="select">
                    <select name="db_driver" id="db_driver" onchange="toggleDb()">
                        <option value="pdo_mysql" <?= !extension_loaded('pdo_mysql')?'disabled':'' ?>>MySQL / MariaDB</option>
                        <option value="pdo_sqlite" <?= !extension_loaded('pdo_sqlite')?'disabled':'' ?>>SQLite</option>
                    </select>
                </div></div>
            </div>
            <div id="mysql_fields">
                <div class="columns">
                    <div class="column is-three-quarters">
                        <div class="field"><label class="label">Hostname</label>
                        <div class="control"><input class="input" type="text" name="db_host" value="localhost"></div></div>
                    </div>
                    <div class="column">
                        <div class="field"><label class="label">Port</label>
                        <div class="control"><input class="input" type="number" name="db_port" value="3306"></div></div>
                    </div>
                </div>
                <div class="field"><label class="label">Datenbankname</label><div class="control"><input class="input" type="text" name="db_name" value="desec_manager"></div></div>
                <div class="field"><label class="label">Benutzer</label><div class="control"><input class="input" type="text" name="db_user" value="desec_user"></div></div>
                <div class="field"><label class="label">Passwort</label><div class="control"><input class="input" type="password" name="db_pass" autocomplete="new-password"></div></div>
                <div class="field"><div class="control"><label class="checkbox">
                    <input type="checkbox" name="db_create" id="db_create" onchange="toggleRoot()">
                    DB + User automatisch anlegen (Root-Zugang nötig)
                </label></div></div>
                <div id="root_fields" style="display:none">
                    <div class="notification is-info is-light is-size-7">Root-Credentials werden nicht gespeichert.</div>
                    <div class="field"><label class="label">Root-Benutzer</label><div class="control"><input class="input" type="text" name="db_root_user" value="root"></div></div>
                    <div class="field"><label class="label">Root-Passwort</label><div class="control"><input class="input" type="password" name="db_root_pass" autocomplete="off"></div></div>
                </div>
            </div>
            <div id="sqlite_fields" style="display:none">
                <div class="field"><label class="label">SQLite-Datei-Pfad</label>
                <div class="control"><input class="input" type="text" name="db_sqlite_path" placeholder="<?= e(PROJECT_ROOT.'/var/database.sqlite') ?>"></div>
                <p class="help">Leer = Standard-Pfad.</p></div>
                <div class="notification is-warning is-light is-size-7 warn-left">SQLite nur für Entwicklung/Einzelnutzer.</div>
            </div>
            <div class="field mt-4"><div class="control">
                <button type="submit" class="button is-primary">Verbindung testen &amp; weiter →</button>
            </div></div>
        </form>
        <script>
        function toggleDb(){var v=document.getElementById('db_driver').value;document.getElementById('mysql_fields').style.display=v==='pdo_mysql'?'':'none';document.getElementById('sqlite_fields').style.display=v==='pdo_sqlite'?'':'none';}
        function toggleRoot(){document.getElementById('root_fields').style.display=document.getElementById('db_create').checked?'':'none';}
        </script>

        <?php elseif ($step===3): /* ── SCHRITT 3: ADMIN ─────────── */ ?>
        <h2 class="title is-5">Schritt 3: Admin-Benutzer</h2>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">
            <div class="field"><label class="label">Benutzername</label><div class="control"><input class="input" type="text" name="admin_user" value="admin" required minlength="3"></div></div>
            <div class="field"><label class="label">E-Mail</label><div class="control"><input class="input" type="email" name="admin_email" required></div></div>
            <div class="field"><label class="label">Passwort <span class="tag is-light is-size-7">mind. 12 Zeichen</span></label><div class="control"><input class="input" type="password" name="admin_pass" required minlength="12" autocomplete="new-password"></div></div>
            <div class="field"><label class="label">Passwort bestätigen</label><div class="control"><input class="input" type="password" name="admin_pass2" required minlength="12" autocomplete="new-password"></div></div>
            <div class="field mt-4"><div class="control"><button type="submit" class="button is-primary">Weiter →</button></div></div>
        </form>

        <?php elseif ($step===4): /* ── SCHRITT 4: APP-EINSTELLUNGEN ─── */ ?>
        <h2 class="title is-5">Schritt 4: Anwendungs-Einstellungen</h2>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">
            <div class="field"><label class="label">App-Name</label><div class="control"><input class="input" type="text" name="app_name" value="DeSEC Manager" required></div></div>
            <div class="field"><label class="label">Hostname <span class="help is-inline ml-2">ohne http://</span></label>
                <div class="control"><input class="input" type="text" name="app_domain" placeholder="manager.example.com"></div>
                <p class="help">Für WebAuthn benötigt.</p></div>
            <div class="field"><label class="label">Theme</label><div class="control"><div class="select"><select name="app_theme">
                <?php foreach($themes as $v=>$l): ?><option value="<?= e($v) ?>" <?= $v==='default'?'selected':'' ?>><?= e($l) ?></option><?php endforeach ?>
            </select></div></div></div>
            <div class="field"><div class="control"><label class="checkbox"><input type="checkbox" name="app_https" checked> HTTPS erzwingen</label></div></div>
            <div class="field mt-4"><div class="control"><button type="submit" class="button is-primary">Weiter →</button></div></div>
        </form>

        <?php elseif ($step===5): /* ── SCHRITT 5: BESTÄTIGUNG ─────── */ ?>
        <?php $db=$_SESSION['install_db']??[];$admin=$_SESSION['install_admin']??[];$app=$_SESSION['install_app']??[]; ?>
        <h2 class="title is-5">Schritt 5: Bestätigung</h2>
        <div class="box">
            <table class="table is-fullwidth is-narrow">
                <tr><th>Datenbank</th><td><?= $db['driver']==='pdo_sqlite'?'SQLite: '.e($db['path']??''):'MySQL: '.e(($db['host']??'').'/'.($db['name']??'')) ?></td></tr>
                <tr><th>Admin</th><td><?= e($admin['username']??'') ?> (<?= e($admin['email']??'') ?>)</td></tr>
                <tr><th>App-Name</th><td><?= e($app['name']??'') ?></td></tr>
                <tr><th>Domain</th><td><?= e($app['domain']??'(leer)') ?></td></tr>
                <tr><th>Theme</th><td><?= e($app['theme']??'default') ?></td></tr>
                <tr><th>HTTPS</th><td><?= !empty($app['https'])?'Ja':'Nein' ?></td></tr>
            </table>
        </div>
        <?php if (!VENDOR_OK): ?><div class="notification is-danger"><strong>vendor/ fehlt!</strong> Bitte <code>composer install</code> ausführen.</div><?php endif ?>
        <div class="notification is-warning warn-left">
            <strong>⚠️ Nach der Installation:</strong> <code>install.php</code> löschen oder sperren!
        </div>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">
            <button type="submit" class="button is-success" <?= !VENDOR_OK?'disabled':'' ?>>✓ Jetzt installieren</button>
        </form>

        <?php elseif ($step===6): /* ── SCHRITT 6: FERTIG ─────────── */ ?>
        <?php $result=$_SESSION['install_result']??[]; ?>
        <div class="notification is-success"><strong>✅ Installation erfolgreich abgeschlossen!</strong></div>
        <div class="box" style="border:2px solid #4caf50">
            <p><strong>Admin-Benutzername:</strong> <?= e($result['admin_user']??'') ?></p>
            <p><strong>Admin-Passwort:</strong>
                <code style="background:#e8f5e9;padding:.3em .6em;border-radius:4px"><?= e($result['admin_pass']??'') ?></code>
            </p>
            <p class="mt-2 is-size-7 has-text-danger">⚠️ Wird nicht erneut angezeigt. Bitte sofort sichern!</p>
        </div>
        <div class="notification is-danger is-light mt-3">
            <strong>Sicherheitshinweis:</strong> <code>install.php</code> jetzt löschen!
        </div>
        <a href="index.php" class="button is-primary mt-2">Zur Anwendung →</a>
        <?php unset($_SESSION['install_result'],$_SESSION['install_step']); ?>

        <?php endif ?>
    </div>
</div>
</body>
</html>
