<?php
declare(strict_types=1);

// ── Vendor-Check (muss vor jedem require stehen) ─────────────────────────────
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    http_response_code(503);
    renderPreInstallPage('vendor_missing');
    exit;
}

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

// ── Keine Konfiguration → Willkommens-/Install-Seite ────────────────────────
$configPath = __DIR__ . '/config/config.php';

if (!file_exists($configPath)) {
    http_response_code(200);
    renderPreInstallPage('not_configured');
    exit;
}

// ── Hilfsfunktion: Vorinstallations-Seite ausgeben ───────────────────────────
function renderPreInstallPage(string $reason): void
{
    $vendorMissing = ($reason === 'vendor_missing');
    $installUrl = htmlspecialchars('install/', ENT_QUOTES, 'UTF-8');
    ?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeSEC Manager</title>
    <link rel="stylesheet" href="assets/css/bulma.min.css">
    <style>
        body { background: linear-gradient(135deg, #e3f0ff 0%, #f0fff4 100%); min-height: 100vh; }
        .hero-logo { font-size: 3rem; }
        .feature-icon { font-size: 1.5rem; margin-right: .5rem; }
        .card-equal { height: 100%; }
    </style>
</head>
<body>

<section class="hero is-medium is-primary is-bold" style="background:linear-gradient(135deg,#1565c0,#0d47a1)">
    <div class="hero-body">
        <div class="container has-text-centered">
            <p class="hero-logo">🛡️</p>
            <h1 class="title is-2 has-text-white">DeSEC Manager</h1>
            <p class="subtitle has-text-white" style="opacity:.9">
                Self-hosted Web-Interface für die <a href="https://desec.io" style="color:#90caf9" target="_blank" rel="noopener">deSEC DNS API</a>
            </p>
        </div>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:900px">

        <?php if ($vendorMissing): ?>
        <div class="notification is-warning is-light mb-5" style="border-left:4px solid #ff9800">
            <p><strong>⚠️ Composer-Abhängigkeiten fehlen.</strong></p>
            <p class="mt-2">Bitte führen Sie im Projektverzeichnis folgenden Befehl aus:</p>
            <pre style="background:#fff3e0;border-radius:6px;padding:.75rem 1rem;margin-top:.5rem"><code>composer install --no-dev</code></pre>
            <p class="mt-2 is-size-7 has-text-grey">Danach diese Seite neu laden.</p>
        </div>
        <?php else: ?>
        <div class="notification is-info is-light mb-5" style="border-left:4px solid #2196f3">
            <p><strong>ℹ️ Noch nicht konfiguriert.</strong></p>
            <p class="mt-2">Der DeSEC Manager wurde noch nicht eingerichtet. Starten Sie den Installations-Assistenten:</p>
            <a href="<?= $installUrl ?>" class="button is-primary mt-3">🛠️ Installation starten</a>
        </div>
        <?php endif; ?>

        <h2 class="title is-4 mt-5 mb-4">Funktionen</h2>
        <div class="columns is-multiline">
            <?php
            $features = [
                ['🌐', 'Domain-Verwaltung',       'Domains bei deSEC anlegen, einsehen und löschen.'],
                ['📋', 'DNS-Record-Editor',        'RRsets direkt im Browser bearbeiten – inkl. Inline-Editor.'],
                ['🔑', 'API-Key-Verwaltung',       'Mehrere deSEC-API-Keys pro Nutzer verwalten und verschlüsselt speichern.'],
                ['👥', 'Benutzerverwaltung',        'Lokale Benutzer mit Admin-Rollen und individualisierten Zugängen.'],
                ['🔒', 'Zwei-Faktor-Authentifizierung', 'TOTP (Google Authenticator) und WebAuthn / Passkeys.'],
                ['🎨', 'Theme-System',              'Anpassbares Design – inkl. Dark-Mode-Unterstützung.'],
            ];
            foreach ($features as [$icon, $title, $desc]): ?>
            <div class="column is-half">
                <div class="box card-equal">
                    <p><span class="feature-icon"><?= $icon ?></span><strong><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <p class="is-size-7 has-text-grey mt-1"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="content mt-5 has-text-centered has-text-grey is-size-7">
            <p>DeSEC Manager &nbsp;·&nbsp; <a href="https://desec.io" target="_blank" rel="noopener">deSEC DNS</a></p>
        </div>
    </div>
</section>

</body>
</html>
    <?php
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
