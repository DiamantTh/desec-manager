<?php
declare(strict_types=1);

use App\Controller\AdminController;
use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\DomainController;
use App\Controller\KeyController;
use App\Controller\ProfileController;
use App\Controller\RecordController;
use App\Database\DatabaseConnection;
use App\Service\ThemeManager;

require_once __DIR__ . '/vendor/autoload.php';

session_start();

// ── Keine Konfiguration → Setup-Hinweis ─────────────────────────────────────
$configPath = __DIR__ . '/config/config.php';

if (!file_exists($configPath)) {
    http_response_code(503);
    ?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation erforderlich — DeSEC Manager</title>
    <link rel="stylesheet" href="assets/css/bulma.min.css">
</head>
<body>
<section class="section">
    <div class="container" style="max-width:600px">
        <div class="notification is-warning">
            <strong>Konfiguration fehlt.</strong><br>
            Bitte führen Sie <a href="install.php"><code>install.php</code></a> aus,
            um die Erstkonfiguration zu erstellen.
        </div>
    </div>
</section>
</body>
</html>
    <?php
    exit;
}

$config = require $configPath;

DatabaseConnection::bootstrap($config);

// ── Theme laden ──────────────────────────────────────────────────────────────
$theme = new ThemeManager($config, __DIR__);

// ── Router ───────────────────────────────────────────────────────────────────
$allowedRoutes = ['dashboard', 'domains', 'records', 'keys', 'profile', 'auth', 'admin'];
$route = $_GET['route'] ?? 'dashboard';
if (!in_array($route, $allowedRoutes, true)) {
    $route = 'dashboard';
}

$controllerMap = [
    'dashboard' => DashboardController::class,
    'domains'   => DomainController::class,
    'records'   => RecordController::class,
    'keys'      => KeyController::class,
    'profile'   => ProfileController::class,
    'auth'      => AuthController::class,
    'admin'     => AdminController::class,
];

// ── Auth Check ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) && $route !== 'auth') {
    header('Location: ?route=auth');
    exit;
}

$controllerClass = $controllerMap[$route];
$controller = new $controllerClass($config);

$appName = htmlspecialchars($config['application']['name'] ?? 'DeSEC Manager', ENT_QUOTES, 'UTF-8');

// ── Dark-Mode: schon beim Seitenaufbau Theme-Skript einbetten ────────────────
// (wird im <head> als Inline-Script eingebettet, um FOUC zu verhindern)
$darkModeInlineScript = '';
if ($theme->supportsDarkMode()) {
    foreach ($theme->getLocalJs() as $jsFile) {
        $absPath = __DIR__ . '/' . $jsFile;
        if (file_exists($absPath)) {
            $code = file_get_contents($absPath);
            if ($code !== false) {
                // Nur den synchronen Teil (getPreference + applyTheme Aufruf) inline ausführen
                $darkModeInlineScript = $code;
            }
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $appName ?></title>

    <?php /* ─── CSS (Bulma + Theme-Stylesheets) ──────────────────────── */ ?>
    <?php foreach ($theme->getExternalCss() as $cssUrl): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>

    <?php /* ─── Lokales Theme-CSS ──────────────────────────────────────── */ ?>
    <?php foreach ($theme->getLocalCss() as $cssFile): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>

    <?php /* ─── JavaScript ──────────────────────────────────────────────── */ ?>
    <?php foreach ($theme->getExternalJs() as $jsUrl): ?>
    <script src="<?= htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php endforeach; ?>

    <?php /* ─── Dark-Mode: Inline-Script (verhindert Flash) ───────────── */ ?>
    <?php if ($darkModeInlineScript !== ''): ?>
    <script><?= $darkModeInlineScript ?></script>
    <?php endif; ?>
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
<nav class="navbar" role="navigation" aria-label="Hauptnavigation">
    <div class="navbar-brand">
        <a class="navbar-item" href="?route=dashboard">
            <strong><?= $appName ?></strong>
        </a>
        <a role="button" class="navbar-burger" aria-label="Menü öffnen"
           aria-expanded="false" data-target="mainNavbar">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </a>
    </div>

    <div id="mainNavbar" class="navbar-menu">
        <div class="navbar-start">
            <a class="navbar-item <?= $route === 'dashboard' ? 'is-active' : '' ?>"
               href="?route=dashboard">Dashboard</a>

            <a class="navbar-item <?= $route === 'domains' ? 'is-active' : '' ?>"
               href="?route=domains">Domains</a>

            <a class="navbar-item <?= $route === 'keys' ? 'is-active' : '' ?>"
               href="?route=keys">API Keys</a>

            <?php if (!empty($_SESSION['is_admin'])): ?>
            <a class="navbar-item <?= $route === 'admin' ? 'is-active' : '' ?>"
               href="?route=admin">Admin</a>
            <?php endif; ?>
        </div>

        <div class="navbar-end">
            <?php if ($theme->supportsDarkMode()): ?>
            <div class="navbar-item">
                <button id="dsec-theme-toggle" title="Dark/Light-Mode wechseln" aria-label="Dark Mode aktivieren">
                    <span id="dsec-theme-icon">🌙</span>
                </button>
            </div>
            <?php endif; ?>

            <div class="navbar-item has-dropdown is-hoverable">
                <a class="navbar-link">
                    <?= htmlspecialchars($_SESSION['username'] ?? 'Benutzer', ENT_QUOTES, 'UTF-8') ?>
                </a>
                <div class="navbar-dropdown is-right">
                    <a class="navbar-item" href="?route=profile">Profil</a>
                    <hr class="navbar-divider">
                    <a class="navbar-item" href="?route=auth&action=logout">Abmelden</a>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="section">
    <div class="container">
        <?php $controller->render(); ?>
    </div>
</main>

<footer class="footer">
    <div class="content has-text-centered">
        <p>
            <strong><?= $appName ?></strong> &nbsp;·&nbsp;
            <small><a href="https://desec.io">deSEC DNS API</a></small>
        </p>
        <p class="mt-2" style="font-size:.75rem; opacity:.6">
            Theme: <?= htmlspecialchars($theme->getDisplayName(), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <p class="mt-3">
            <label class="checkbox" title="Inline-Editor für RRsets aktivieren">
                <input type="checkbox" id="inline-editor-toggle">
                Inline-Editor <span class="tag is-warning is-light ml-1">Beta</span>
            </label>
        </p>
    </div>
</footer>

<!-- App-Basisskript -->
<script src="assets/js/app.js"></script>

<?php /* Routen-spezifisches JS */ ?>
<?php if (file_exists(__DIR__ . "/assets/js/{$route}.js")): ?>
<script src="assets/js/<?= htmlspecialchars($route, ENT_QUOTES, 'UTF-8') ?>.js"></script>
<?php endif; ?>

<?php /* WebAuthn */ ?>
<?php if ($route === 'auth' || $route === 'profile'): ?>
<script src="assets/js/webauthn.js"></script>
<?php endif; ?>

<?php /* Theme-JS (Dark-Mode Toggle etc.) — wird auch inline genutzt, aber als Datei nachgeladen für nicht-inline-Funktionen */ ?>
<?php if ($theme->supportsDarkMode()): ?>
<?php foreach ($theme->getLocalJs() as $jsFile): ?>
<?php /* Inline bereits gerendert; skip hier um Doppelausführung zu vermeiden */ ?>
<?php endforeach; ?>
<?php endif; ?>

</body>
</html>
