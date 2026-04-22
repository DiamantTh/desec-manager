<?php

declare(strict_types=1);

/**
 * Installer – Phase 2: Konfiguration
 * Datenbank · Admin-Benutzer · Anwendungs-Einstellungen
 * Handler + View
 */

/** @return string[] */
function processStep2(): array
{
    $errors = [];

    // ── Datenbank ─────────────────────────────────────────────────────────────
    $driver = (string) ($_POST['db_driver'] ?? 'pdo_mysql');
    if (!in_array($driver, ['pdo_mysql', 'pdo_sqlite', 'pdo_pgsql'], true)) {
        $errors[] = t('step2.invalid_driver');
        return $errors;
    }

    if ($driver === 'pdo_sqlite') {
        $path = trim((string) ($_POST['db_sqlite_path'] ?? ''));
        if ($path === '') {
            $path = PROJECT_ROOT . '/var/database.sqlite';
        }
        if (str_contains($path, "\0") || strlen($path) > 500) {
            $errors[] = t('step2.invalid_sqlite_path');
            return $errors;
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            $errors[] = t('step2.dir_create_failed', e($dir));
            return $errors;
        }
        try {
            new PDO('sqlite:' . $path);
        } catch (\Exception $ex) {
            $errors[] = t('step2.sqlite_error', e($ex->getMessage()));
            return $errors;
        }
        $_SESSION['install_db'] = ['driver' => 'pdo_sqlite', 'path' => $path];

    } elseif ($driver === 'pdo_pgsql') {
        $host = trim((string) ($_POST['pg_host'] ?? 'localhost'));
        $port = (int) ($_POST['pg_port'] ?? 5432);
        $name = trim((string) ($_POST['pg_name'] ?? ''));
        $user = trim((string) ($_POST['pg_user'] ?? ''));
        $pass = (string) ($_POST['pg_pass'] ?? '');

        if ($host === '' || strlen($host) > 255) {
            $errors[] = t('step2.host_invalid');
        }
        if ($port < 1 || $port > 65535) {
            $errors[] = t('step2.port_invalid');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name) || strlen($name) > 63) {
            $errors[] = t('step2.dbname_invalid_63');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $user) || strlen($user) > 63) {
            $errors[] = t('step2.dbuser_invalid_63');
        }
        if (!empty($errors)) {
            return $errors;
        }

        try {
            $pdo = new PDO(
                "pgsql:host={$host};port={$port};dbname={$name}",
                $user, $pass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            unset($pdo);
        } catch (\Exception $ex) {
            $errors[] = t('step2.pgsql_error', e($ex->getMessage()));
            return $errors;
        }

        $_SESSION['install_db'] = [
            'driver' => 'pdo_pgsql',
            'host'   => $host,
            'port'   => $port,
            'name'   => $name,
            'user'   => $user,
            'pass'   => $pass,
        ];

    } else {
        // MySQL / MariaDB
        $host   = trim((string) ($_POST['db_host'] ?? 'localhost'));
        $port   = (int) ($_POST['db_port'] ?? 3306);
        $name   = trim((string) ($_POST['db_name'] ?? ''));
        $user   = trim((string) ($_POST['db_user'] ?? ''));
        $pass   = (string) ($_POST['db_pass'] ?? '');
        $create = !empty($_POST['db_create']);

        if ($host === '' || strlen($host) > 255) {
            $errors[] = t('step2.host_invalid');
        }
        if ($port < 1 || $port > 65535) {
            $errors[] = t('step2.port_invalid');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name) || strlen($name) > 64) {
            $errors[] = t('step2.dbname_invalid_64');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $user) || strlen($user) > 32) {
            $errors[] = t('step2.dbuser_invalid_32');
        }
        if (!empty($errors)) {
            return $errors;
        }

        if ($create) {
            $rootUser = trim((string) ($_POST['db_root_user'] ?? 'root'));
            $rootPass = (string) ($_POST['db_root_pass'] ?? '');
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $rootUser) || strlen($rootUser) > 32) {
                $errors[] = t('step2.root_user_invalid');
                return $errors;
            }
            try {
                $rootPdo = new PDO(
                    "mysql:host={$host};port={$port};charset=utf8mb4",
                    $rootUser, $rootPass,
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                );
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $rootPdo->exec("CREATE USER IF NOT EXISTS '{$user}'@'%' IDENTIFIED BY " . $rootPdo->quote($pass));
                $rootPdo->exec("GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$user}'@'%'");
                $rootPdo->exec("FLUSH PRIVILEGES");
            } catch (\Exception $ex) {
                $errors[] = t('step2.root_error', e($ex->getMessage()));
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
            $errors[] = t('step2.mysql_error', e($ex->getMessage()));
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
        $errors[] = t('step2.admin_user_invalid');
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminEmail) > 254) {
        $errors[] = t('step2.admin_email_invalid');
    }
    if (strlen($adminPass) < 12) {
        $errors[] = t('step2.admin_pass_short');
    }
    if ($adminPass !== $adminPass2) {
        $errors[] = t('step2.admin_pass_mismatch');
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
        $errors[] = t('step2.app_name_invalid');
    }
    if ($appDomain !== '' && !preg_match('/^[a-zA-Z0-9.\-]{1,253}$/', $appDomain)) {
        $errors[] = t('step2.app_domain_invalid');
    }

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

/**
 * View für Schritt 2.
 *
 * @param array<string, string> $themes
 */
function renderStep2(array $themes): void
{
    ?>
    <h2 class="title is-5"><?= e(t('step2.heading')) ?></h2>
    <p class="mb-4 has-text-grey is-size-7"><?= t('step2.subheading') ?></p>

    <form method="post" id="config-form">
        <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">

        <?php /* ── Abschnitt A: Datenbank ── */ ?>
        <p class="section-title">🗄️ <?= e(t('step2.db_section')) ?></p>

        <div class="field">
            <label class="label"><?= e(t('step2.db_driver')) ?></label>
            <div class="control"><div class="select">
                <select name="db_driver" id="db_driver" onchange="toggleDb()">
                    <option value="pdo_mysql"  <?= !extension_loaded('pdo_mysql')  ? 'disabled' : '' ?>>MySQL / MariaDB</option>
                    <option value="pdo_pgsql"  <?= !extension_loaded('pdo_pgsql')  ? 'disabled' : '' ?>>PostgreSQL</option>
                    <option value="pdo_sqlite" <?= !extension_loaded('pdo_sqlite') ? 'disabled' : '' ?>>SQLite</option>
                </select>
            </div></div>
        </div>

        <div id="mysql_fields">
            <div class="columns">
                <div class="column is-three-quarters">
                    <div class="field">
                        <label class="label"><?= e(t('step2.hostname')) ?></label>
                        <div class="control"><input class="input" type="text" name="db_host" value="localhost" maxlength="255"></div>
                    </div>
                </div>
                <div class="column">
                    <div class="field">
                        <label class="label"><?= e(t('step2.port')) ?></label>
                        <div class="control"><input class="input" type="number" name="db_port" value="3306" min="1" max="65535"></div>
                    </div>
                </div>
            </div>
            <div class="field">
                <label class="label"><?= e(t('step2.dbname')) ?> <span class="has-text-grey is-size-7">(a-z, 0-9, _)</span></label>
                <div class="control"><input class="input" type="text" name="db_name" value="desec_manager" pattern="[a-zA-Z0-9_]+" maxlength="64"></div>
            </div>
            <div class="field">
                <label class="label"><?= e(t('step2.dbuser')) ?> <span class="has-text-grey is-size-7">(a-z, 0-9, _)</span></label>
                <div class="control"><input class="input" type="text" name="db_user" value="desec_user" pattern="[a-zA-Z0-9_]+" maxlength="32"></div>
            </div>
            <div class="field">
                <label class="label"><?= e(t('step2.dbpass')) ?></label>
                <div class="control"><input class="input" type="password" name="db_pass" autocomplete="new-password"></div>
            </div>
            <div class="field">
                <div class="control">
                    <label class="checkbox">
                        <input type="checkbox" name="db_create" id="db_create" onchange="toggleRoot()">
                        <?= e(t('step2.db_create')) ?>
                    </label>
                </div>
            </div>
            <div id="root_fields" style="display:none">
                <div class="notification is-info is-light is-size-7"><?= e(t('step2.root_hint')) ?></div>
                <div class="field">
                    <label class="label"><?= e(t('step2.root_user')) ?></label>
                    <div class="control"><input class="input" type="text" name="db_root_user" value="root" pattern="[a-zA-Z0-9_]+" maxlength="32"></div>
                </div>
                <div class="field">
                    <label class="label"><?= e(t('step2.root_pass')) ?></label>
                    <div class="control"><input class="input" type="password" name="db_root_pass" autocomplete="off"></div>
                </div>
            </div>
        </div>

        <div id="pgsql_fields" style="display:none">
            <div class="columns">
                <div class="column is-three-quarters">
                    <div class="field">
                        <label class="label"><?= e(t('step2.hostname')) ?></label>
                        <div class="control"><input class="input" type="text" name="pg_host" value="localhost" maxlength="255"></div>
                    </div>
                </div>
                <div class="column">
                    <div class="field">
                        <label class="label"><?= e(t('step2.port')) ?></label>
                        <div class="control"><input class="input" type="number" name="pg_port" value="5432" min="1" max="65535"></div>
                    </div>
                </div>
            </div>
            <div class="field">
                <label class="label"><?= e(t('step2.dbname')) ?> <span class="has-text-grey is-size-7">(a-z, 0-9, _)</span></label>
                <div class="control"><input class="input" type="text" name="pg_name" value="desec_manager" pattern="[a-zA-Z0-9_]+" maxlength="63"></div>
            </div>
            <div class="field">
                <label class="label"><?= e(t('step2.dbuser')) ?> <span class="has-text-grey is-size-7">(a-z, 0-9, _)</span></label>
                <div class="control"><input class="input" type="text" name="pg_user" value="desec_user" pattern="[a-zA-Z0-9_]+" maxlength="63"></div>
            </div>
            <div class="field">
                <label class="label"><?= e(t('step2.dbpass')) ?></label>
                <div class="control"><input class="input" type="password" name="pg_pass" autocomplete="new-password"></div>
            </div>
            <div class="notification is-info is-light is-size-7"><?= e(t('step2.pgsql_hint')) ?></div>
        </div>

        <div id="sqlite_fields" style="display:none">
            <div class="field">
                <label class="label"><?= e(t('step2.sqlite_path')) ?></label>
                <div class="control">
                    <input class="input" type="text" name="db_sqlite_path"
                           placeholder="<?= e(PROJECT_ROOT . '/var/database.sqlite') ?>" maxlength="500">
                </div>
                <p class="help"><?= e(t('step2.sqlite_hint')) ?></p>
            </div>
        </div>

        <hr class="section-divider">

        <?php /* ── Abschnitt B: Admin-Benutzer ── */ ?>
        <p class="section-title">👤 <?= e(t('step2.admin_section')) ?></p>

        <div class="columns">
            <div class="column">
                <div class="field">
                    <label class="label"><?= e(t('step2.admin_username')) ?> <span class="has-text-grey is-size-7">(3–50)</span></label>
                    <div class="control"><input class="input" type="text" name="admin_user" value="admin"
                         required pattern="[a-zA-Z0-9_\-\.]{3,50}" minlength="3" maxlength="50"></div>
                </div>
            </div>
            <div class="column">
                <div class="field">
                    <label class="label"><?= e(t('step2.admin_email')) ?></label>
                    <div class="control"><input class="input" type="email" name="admin_email" required maxlength="254"></div>
                </div>
            </div>
        </div>
        <div class="columns">
            <div class="column">
                <div class="field">
                    <label class="label"><?= e(t('step2.admin_pass')) ?> <span class="tag is-light is-size-7"><?= e(t('step2.admin_pass_min')) ?></span></label>
                    <div class="control"><input class="input" type="password" name="admin_pass"
                         required minlength="12" autocomplete="new-password" id="admin_pass"></div>
                </div>
            </div>
            <div class="column">
                <div class="field">
                    <label class="label"><?= e(t('step2.admin_pass_confirm')) ?></label>
                    <div class="control"><input class="input" type="password" name="admin_pass2"
                         required minlength="12" autocomplete="new-password" id="admin_pass2"></div>
                </div>
            </div>
        </div>
        <p id="pass-mismatch" class="help is-danger" style="display:none">
            ⚠️ <?= e(t('step2.pass_mismatch_live')) ?>
        </p>

        <hr class="section-divider">

        <?php /* ── Abschnitt C: Anwendungs-Einstellungen ── */ ?>
        <p class="section-title">⚙️ <?= e(t('step2.app_section')) ?></p>

        <div class="columns">
            <div class="column">
                <div class="field">
                    <label class="label"><?= e(t('step2.app_name')) ?></label>
                    <div class="control"><input class="input" type="text" name="app_name"
                         value="DeSEC Manager" required maxlength="100"></div>
                </div>
            </div>
            <div class="column">
                <div class="field">
                    <label class="label"><?= e(t('step2.app_domain')) ?> <span class="help is-inline ml-2"><?= e(t('step2.app_domain_hint')) ?></span></label>
                    <div class="control"><input class="input" type="text" name="app_domain"
                         placeholder="manager.example.com" maxlength="253" pattern="[a-zA-Z0-9.\-]*"></div>
                    <p class="help"><?= e(t('step2.app_domain_help')) ?></p>
                </div>
            </div>
        </div>
        <div class="columns">
            <div class="column is-half">
                <div class="field">
                    <label class="label"><?= e(t('step2.app_theme')) ?></label>
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
                        <label class="checkbox">
                            <input type="checkbox" name="app_https" checked>
                            <?= e(t('step2.app_https')) ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="field mt-5">
            <div class="control">
                <button type="submit" class="button is-primary is-medium" id="submit-btn">
                    <?= e(t('step2.btn_next')) ?>
                </button>
            </div>
        </div>
    </form>

    <script>
    function toggleDb() {
        var v = document.getElementById('db_driver').value;
        document.getElementById('mysql_fields').style.display  = v === 'pdo_mysql'  ? '' : 'none';
        document.getElementById('pgsql_fields').style.display  = v === 'pdo_pgsql'  ? '' : 'none';
        document.getElementById('sqlite_fields').style.display = v === 'pdo_sqlite' ? '' : 'none';
    }
    function toggleRoot() {
        document.getElementById('root_fields').style.display =
            document.getElementById('db_create').checked ? '' : 'none';
    }
    ['admin_pass','admin_pass2'].forEach(function(id) {
        document.getElementById(id).addEventListener('input', function() {
            var p1  = document.getElementById('admin_pass').value;
            var p2  = document.getElementById('admin_pass2').value;
            var msg = document.getElementById('pass-mismatch');
            msg.style.display = (p1 && p2 && p1 !== p2) ? '' : 'none';
        });
    });
    </script>
    <?php
}
