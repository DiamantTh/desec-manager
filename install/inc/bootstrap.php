<?php

declare(strict_types=1);

/**
 * Installer – Bootstrap
 * Initialisiert Konstanten, lädt Helfer + i18n, startet Session,
 * prüft Token-Auth und CSRF.
 */

// ── Pfade ────────────────────────────────────────────────────────────────────
define('PROJECT_ROOT', dirname(__DIR__, 2));
define('INSTALL_DIR',  dirname(__DIR__));
define('LOCK_FILE',    INSTALL_DIR . '/.lock');
define('TOKEN_FILE',   INSTALL_DIR . '/.install_token');
define('VENDOR_OK',    file_exists(PROJECT_ROOT . '/vendor/autoload.php'));

// ── Autoload (für laminas-i18n) ──────────────────────────────────────────────
if (VENDOR_OK) {
    require_once PROJECT_ROOT . '/vendor/autoload.php';
}

// ── Hilfsfunktionen & i18n ───────────────────────────────────────────────────
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/i18n.php';

// ── Bereits installiert? (Lock-Datei) ────────────────────────────────────────
if (file_exists(LOCK_FILE)) {
    // Session kurz für Spracherkennung starten
    session_name('desec_installer');
    session_start();
    initTranslator();
    require __DIR__ . '/views/locked.php';
    exit;
}

session_name('desec_installer');
session_start();

// i18n initialisieren (nach session_start, damit Session-Locale lesbar ist)
$GLOBALS['_installer_locale'] = initTranslator();

// ── Neustart ─────────────────────────────────────────────────────────────────
if (isset($_GET['restart'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

// ── Install-Token-Schutz ─────────────────────────────────────────────────────
if (!file_exists(TOKEN_FILE)) {
    $written = file_put_contents(TOKEN_FILE, bin2hex(random_bytes(32)));
    if ($written === false) {
        http_response_code(500);
        die('Installer-Token konnte nicht erstellt werden. Bitte Schreibrecht für '
            . htmlspecialchars(INSTALL_DIR, ENT_QUOTES, 'UTF-8') . ' prüfen.');
    }
    chmod(TOKEN_FILE, 0600);
}

if (empty($_SESSION['install_auth'])) {
    $savedToken = file_exists(TOKEN_FILE) ? trim((string) file_get_contents(TOKEN_FILE)) : '';
    if ($savedToken === '') {
        http_response_code(500);
        die('Installer-Token konnte nicht gelesen werden. Bitte Dateirechte prüfen: '
            . htmlspecialchars(TOKEN_FILE, ENT_QUOTES, 'UTF-8'));
    }
    $providedToken = (string) ($_GET['token'] ?? $_POST['install_token'] ?? '');
    if (!hash_equals($savedToken, $providedToken)) {
        require __DIR__ . '/views/access_denied.php';
        exit;
    }
    $_SESSION['install_auth'] = true;
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
