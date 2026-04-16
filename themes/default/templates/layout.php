<?php
/**
 * Haupt-Layout des Default-Themes.
 *
 * Verfügbare Variablen (werden von AbstractHandler::render() übergeben):
 *   @var string                   $content        Bereits gerenderter Seiten-Inhalt (Fragment)
 *   @var \App\Service\ThemeManager $theme          ThemeManager-Instanz
 *   @var \App\Session\SessionContext $sessionContext Session-Zustand
 *   @var string                   $currentPath    Aktueller URL-Pfad (z. B. '/dashboard')
 *   @var string                   $csrfToken      CSRF-Token für Logout-Formular etc.
 */

declare(strict_types=1);

$appName = htmlspecialchars($theme->getAppName(), ENT_QUOTES, 'UTF-8');
$isLoggedIn = $sessionContext->has('user_id');
$username   = $isLoggedIn ? htmlspecialchars((string)$sessionContext->get('username', ''), ENT_QUOTES, 'UTF-8') : '';
$isAdmin    = $isLoggedIn && (bool)$sessionContext->get('is_admin', false);
$csrfSafe   = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');

// Aktive Navigation ermitteln
$navActive = static function (string $prefix) use ($currentPath): bool {
    return str_starts_with($currentPath, $prefix);
};

// Route-spezifisches JS bestimmen
$jsRoute = match (true) {
    str_starts_with($currentPath, '/dashboard')           => 'dashboard',
    str_starts_with($currentPath, '/domains') && !str_contains($currentPath, '/records') => 'domains',
    str_contains($currentPath, '/records')                => 'records',
    str_starts_with($currentPath, '/keys')                => 'keys',
    str_starts_with($currentPath, '/admin')               => 'admin',
    str_starts_with($currentPath, '/profile')             => 'profile',
    str_starts_with($currentPath, '/auth')                => 'auth',
    default                                               => '',
};
$needsWebAuthn = in_array($jsRoute, ['auth', 'profile'], true);

// Dark-Mode-Inline-Script lesen (FOUC-Prevention)
$darkModeScript = '';
if ($theme->supportsDarkMode()) {
    foreach ($theme->getLocalJs() as $jsFile) {
        // $jsFile ist z.B. "themes/default/js/theme.js" — relativ zum Webroot
        $absPath = dirname(__DIR__, 3) . '/' . $jsFile;
        if (file_exists($absPath)) {
            $code = file_get_contents($absPath);
            if ($code !== false) {
                $darkModeScript = $code;
            }
        }
        break; // Nur das erste lokale JS-File für Dark-Mode verwenden
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $appName ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">

    <?php foreach ($theme->getExternalCss() as $cssUrl): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>

    <?php foreach ($theme->getLocalCss() as $cssFile): ?>
    <link rel="stylesheet" href="/<?= htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>

    <?php foreach ($theme->getExternalJs() as $jsUrl): ?>
    <script src="<?= htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php endforeach; ?>

    <?php if ($darkModeScript !== ''): ?>
    <script><?= $darkModeScript ?></script>
    <?php endif; ?>
</head>
<body>

<?php if ($isLoggedIn): ?>
<nav class="navbar" role="navigation" aria-label="Hauptnavigation">
    <div class="navbar-brand">
        <a class="navbar-item" href="/dashboard">
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
            <a class="navbar-item <?= $navActive('/dashboard') ? 'is-active' : '' ?>"
               href="/dashboard"><?= __('Dashboard') ?></a>

            <a class="navbar-item <?= $navActive('/domains') ? 'is-active' : '' ?>"
               href="/domains"><?= __('Domains') ?></a>

            <a class="navbar-item <?= $navActive('/keys') ? 'is-active' : '' ?>"
               href="/keys"><?= __('API Keys') ?></a>

            <?php if ($isAdmin): ?>
            <a class="navbar-item <?= $navActive('/admin') ? 'is-active' : '' ?>"
               href="/admin"><?= __('Admin') ?></a>
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
                <a class="navbar-link"><?= __('Language') ?></a>
                <div class="navbar-dropdown is-right">
                    <?php foreach (\App\Service\Translator::SUPPORTED_LOCALES as $code => $name): ?>
                    <form method="post" action="<?= htmlspecialchars($currentPath, ENT_QUOTES, 'UTF-8') ?>" style="display:inline">
                        <input type="hidden" name="_locale" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="navbar-item" style="border:none;background:none;cursor:pointer;width:100%;text-align:left;padding:0.375rem 1rem">
                            <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                            <?php if (\App\Service\Translator::getCurrentLocale() === $code): ?>
                            <span class="tag is-primary is-small ml-1">✓</span>
                            <?php endif; ?>
                        </button>
                    </form>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="navbar-item has-dropdown is-hoverable">
                <a class="navbar-link"><?= $username ?></a>
                <div class="navbar-dropdown is-right">
                    <a class="navbar-item <?= $navActive('/profile') ? 'is-active' : '' ?>"
                       href="/profile"><?= __('Profile') ?></a>
                    <hr class="navbar-divider">
                    <div class="navbar-item" style="padding:0">
                        <form method="post" action="/auth/logout" style="width:100%">
                            <input type="hidden" name="csrf" value="<?= $csrfSafe ?>">
                            <button type="submit"
                                    style="width:100%;background:none;border:none;cursor:pointer;padding:0.375rem 1rem;text-align:left;font-size:inherit;color:inherit"
                                    class="has-text-danger">
                                <?= __('Sign out') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="section">
    <div class="container">
        <?= $content ?>
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
        <?php if (str_contains($currentPath, '/records') || str_contains($currentPath, '/domains')): ?>
        <p class="mt-3">
            <label class="checkbox" title="Inline-Editor für RRsets aktivieren">
                <input type="checkbox" id="inline-editor-toggle">
                Inline-Editor <span class="tag is-warning is-light ml-1">Beta</span>
            </label>
        </p>
        <?php endif; ?>
    </div>
</footer>

<script src="/assets/js/app.js"></script>

<?php if (in_array($jsRoute, ['admin', 'profile'], true)): ?>
<script src="/themes/default/js/pwtools.bundle.js"></script>
<?php endif; ?>

<?php if ($jsRoute !== '' && file_exists(dirname(__DIR__, 3) . '/assets/js/' . $jsRoute . '.js')): ?>
<script src="/assets/js/<?= htmlspecialchars($jsRoute, ENT_QUOTES, 'UTF-8') ?>.js"></script>
<?php endif; ?>

<?php if ($needsWebAuthn): ?>
<script src="/assets/js/webauthn.js"></script>
<?php endif; ?>

<?php foreach ($theme->getSvelteBundles() as $bundle): ?>
<script type="module" src="/<?= htmlspecialchars($bundle, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>

</body>
</html>
