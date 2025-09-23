<?php
declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\DomainController;
use App\Controller\KeyController;
use App\Controller\ProfileController;
use App\Controller\RecordController;
use App\Controller\AdminController;
use App\Database\DatabaseConnection;

require_once __DIR__ . '/vendor/autoload.php';

session_start();

$configPath = __DIR__ . '/config/config.php';

if (!file_exists($configPath)) {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Installation erforderlich</title>';
    echo '<link rel="stylesheet" href="assets/css/bulma.min.css">';
    echo '</head><body><section class="section"><div class="container">';
    echo '<div class="notification is-warning"><strong>Konfiguration fehlt.</strong> '; 
    echo 'Bitte führen Sie <code>install.php</code> aus, um <code>config/config.php</code> zu erzeugen.</div>';
    echo '</div></section></body></html>';
    exit;
}

$config = require $configPath;

DatabaseConnection::bootstrap($config);

// Router
$route = $_GET['route'] ?? 'dashboard';

$controllerMap = [
    'dashboard' => DashboardController::class,
    'domains' => DomainController::class,
    'records' => RecordController::class,
    'keys' => KeyController::class,
    'profile' => ProfileController::class,
    'auth' => AuthController::class,
    'admin' => AdminController::class,
];

// Auth Check
if (!isset($_SESSION['user_id']) && $route !== 'auth') {
    header('Location: ?route=auth');
    exit;
}

$controllerClass = $controllerMap[$route] ?? DashboardController::class;
$controller = new $controllerClass($config);

// Layout
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeSEC Manager</title>
    <link rel="stylesheet" href="assets/css/bulma.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <?php if (file_exists(__DIR__ . "/assets/css/{$route}.css")): ?>
    <link rel="stylesheet" href="assets/css/<?= $route ?>.css">
    <?php endif; ?>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="navbar is-light" role="navigation" aria-label="main navigation">
        <div class="navbar-brand">
            <a class="navbar-item" href="?route=dashboard">
                <strong>DeSEC Manager</strong>
            </a>
        </div>

        <div class="navbar-menu">
            <div class="navbar-start">
                <a class="navbar-item <?= $route === 'dashboard' ? 'is-active' : '' ?>"
                   href="?route=dashboard">
                    Dashboard
                </a>

                <a class="navbar-item <?= $route === 'domains' ? 'is-active' : '' ?>"
                   href="?route=domains">
                    Domains
                </a>

                <a class="navbar-item <?= $route === 'keys' ? 'is-active' : '' ?>"
                   href="?route=keys">
                    API Keys
                </a>

                <?php if (!empty($_SESSION['is_admin'])): ?>
                <a class="navbar-item <?= $route === 'admin' ? 'is-active' : '' ?>"
                   href="?route=admin">
                    Admin
                </a>
                <?php endif; ?>
            </div>

            <div class="navbar-end">
                <div class="navbar-item has-dropdown is-hoverable">
                    <a class="navbar-link">
                        <?= htmlspecialchars($_SESSION['username'] ?? 'Benutzer') ?>
                    </a>

                    <div class="navbar-dropdown is-right">
                        <a class="navbar-item" href="?route=profile">
                            Profil
                        </a>
                        <hr class="navbar-divider">
                        <a class="navbar-item" href="?route=auth&action=logout">
                            Abmelden
                        </a>
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
                <strong>DeSEC Manager</strong><br>
                <small>Ein Interface für die <a href="https://desec.io">deSEC DNS API</a></small>
            </p>
            <p class="mt-3">
                <label class="checkbox" title="Inline-Editor für RRsets aktivieren">
                    <input type="checkbox" id="inline-editor-toggle">
                    Inline-Editor <span class="tag is-warning is-light ml-1">Beta</span>
                </label>
            </p>
        </div>
    </footer>

    <!-- Base Scripts -->
    <script src="assets/js/app.js"></script>
    <?php if (file_exists(__DIR__ . "/assets/js/{$route}.js")): ?>
    <script src="assets/js/<?= $route ?>.js"></script>
    <?php endif; ?>

    <!-- WebAuthn Support -->
    <?php if ($route === 'auth' || $route === 'profile'): ?>
    <script src="assets/js/webauthn.js"></script>
    <?php endif; ?>
</body>
</html>
