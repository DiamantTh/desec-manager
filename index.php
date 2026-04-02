<?php

declare(strict_types=1);

// ── Vendor-Check (muss vor jedem require stehen) ─────────────────────────────
// HINWEIS: renderPreInstallPage() ist weiter unten definiert – PHP hoistet Funktionen.
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    http_response_code(503);
    renderPreInstallPage('vendor_missing');
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// ── Konfigurations-Check ──────────────────────────────────────────────────────
// Installiert gilt als: config.local.toml ODER install/.lock ODER legacy config.php
$isConfigured =
    file_exists(__DIR__ . '/config/config.local.toml') ||
    file_exists(__DIR__ . '/install/.lock')             ||
    file_exists(__DIR__ . '/config/config.php');

if (!$isConfigured) {
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
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="stylesheet" href="assets/css/bulma.min.css">
    <style>
        /* ── Layout ──────────────────────────────────────────── */
        body {
            background-color: #f0f7f0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Ccircle cx='30' cy='30' r='1.5' fill='%234caf50' opacity='0.18'/%3E%3Ccircle cx='0' cy='0' r='1' fill='%231565c0' opacity='0.1'/%3E%3Ccircle cx='60' cy='0' r='1' fill='%231565c0' opacity='0.1'/%3E%3Ccircle cx='0' cy='60' r='1' fill='%231565c0' opacity='0.1'/%3E%3Ccircle cx='60' cy='60' r='1' fill='%231565c0' opacity='0.1'/%3E%3C/svg%3E");
            min-height: 100vh;
        }
        /* ── Hero ────────────────────────────────────────────── */
        .dsec-hero {
            background: linear-gradient(135deg, #1b5e20 0%, #1565c0 60%, #0d47a1 100%);
            padding: 2rem 1.5rem;
        }
        .dsec-hero .title   { color: #ffffff; font-size: 1.75rem; margin-bottom: .35rem; }
        .dsec-hero .subtitle { color: #c8e6c9; font-size: .95rem; opacity: 1; }
        .dsec-hero a { color: #81d4fa; text-decoration: underline; }
        /* ── Feature cards ───────────────────────────────────── */
        .feature-icon { font-size: 1.4rem; margin-right: .4rem; }
        .card-equal { height: 100%; border-left: 3px solid #4caf50; background-color: #ffffff; color: #1a1a1a; }
        .card-equal strong { color: #1b5e20; }
        /* ── Footer ──────────────────────────────────────────── */
        .dsec-footer {
            background: #1b2a1b;
            color: #b2dfb2;
            padding: 1.75rem 1.5rem;
            font-size: .85rem;
        }
        .dsec-footer a { color: #81c784; }
        .dsec-footer .footer-title { color: #ffffff; font-weight: 700; margin-bottom: .4rem; }
        .dsec-footer .tag.is-success { background: #2e7d32; color: #fff; }
        /* Bulma v5 überschreibt bei prefers-color-scheme:dark die scheme-Variablen.
         * Da dieses Interface ein reines Light-Theme hat, setzen wir sie im selben
         * Media-Query zurück – unser <style> kommt nach bulma.min.css und gewinnt. */
        @media (prefers-color-scheme: dark) {
            :root {
                --bulma-scheme-brightness: light;
                --bulma-scheme-main-l: 100%;
                --bulma-scheme-main-bis-l: 98%;
                --bulma-scheme-main-ter-l: 96%;
                --bulma-background-l: 96%;
                --bulma-border-weak-l: 93%;
                --bulma-border-l: 86%;
                --bulma-text-weak-l: 48%;
                --bulma-text-strong-l: 21%;
                --bulma-text-title-l: 14%;
                --bulma-scheme-invert-ter-l: 14%;
                --bulma-scheme-invert-bis-l: 7%;
                --bulma-scheme-invert-l: 4%;
                --bulma-text-l: 29%;
            }
        }
    </style>
</head>
<body>

<section class="dsec-hero">
    <div class="container">
        <div class="columns is-vcentered">
            <div class="column is-narrow has-text-centered-mobile">
                <img src="assets/img/logo.svg" alt="DeSEC Manager Logo" width="72" height="72">
            </div>
            <div class="column">
                <h1 class="title">DeSEC Manager</h1>
                <p class="subtitle">
                    Self-hosted Web-Interface für die
                    <a href="https://desec.io" target="_blank" rel="noopener">deSEC DNS API</a>
                </p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:900px">

        <?php if ($vendorMissing): ?>
        <div class="notification" style="background:#fff8e1;border-left:4px solid #f9a825;color:#4e3900" role="alert">
            <p><strong>⚠️ Composer-Abhängigkeiten fehlen.</strong></p>
            <p class="mt-2">Bitte führen Sie im Projektverzeichnis folgenden Befehl aus:</p>
            <pre style="background:#fffde7;border-radius:6px;padding:.75rem 1rem;margin-top:.5rem;color:#333"><code>composer install --no-dev</code></pre>
            <p class="mt-2 is-size-7 has-text-grey-dark">Danach diese Seite neu laden.</p>
        </div>
        <?php else: ?>
        <div class="notification" style="background:#e8f5e9;border-left:4px solid #2e7d32;color:#1b3a20" role="status">
            <p><strong>ℹ️ Noch nicht konfiguriert.</strong></p>
            <p class="mt-2">Der DeSEC Manager wurde noch nicht eingerichtet. Starten Sie den Installations-Assistenten:</p>
            <a href="<?= $installUrl ?>" class="button mt-3"
               style="background:#2e7d32;color:#fff;border-color:#2e7d32">🛠️ Installation starten</a>
        </div>
        <?php endif; ?>

        <h2 class="title is-5 mt-5 mb-4" style="color:#1b5e20">Funktionen</h2>
        <div class="columns is-multiline">
            <?php
            $features = [
                ['🌐', 'Domain-Verwaltung',              'Domains bei deSEC anlegen, einsehen und löschen.'],
                ['📋', 'DNS-Record-Editor',               'RRsets direkt im Browser bearbeiten – inkl. Inline-Editor.'],
                ['🔑', 'API-Key-Verwaltung',              'Mehrere deSEC-API-Keys pro Nutzer verwalten und verschlüsselt speichern.'],
                ['👥', 'Benutzerverwaltung',               'Lokale Benutzer mit Admin-Rollen und individualisierten Zugängen.'],
                ['🔒', 'Zwei-Faktor-Authentifizierung',   'TOTP (Google Authenticator) und WebAuthn / Passkeys.'],
                ['🎨', 'Theme-System',                    'Anpassbares Design – inkl. Dark-Mode-Unterstützung.'],
            ];
            foreach ($features as [$icon, $title, $desc]): ?>
            <div class="column is-half">
                <div class="box card-equal">
                    <p><span class="feature-icon" aria-hidden="true"><?= $icon ?></span><strong><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <p class="is-size-7 mt-1" style="color:#374151"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <footer class="dsec-footer mt-6">
            <div class="columns is-vcentered">
                <div class="column">
                    <p class="footer-title">DeSEC Manager</p>
                    <p>
                        Ein selbst-gehostetes Web-Interface zur Verwaltung von DNS-Zonen
                        über die <a href="https://desec.io" target="_blank" rel="noopener">deSEC DNS API</a>.
                        Unterstützt mehrere Benutzer, verschlüsselte API-Keys,
                        2FA (TOTP &amp; WebAuthn) und anpassbare Themes.
                    </p>
                </div>
                <div class="column is-narrow has-text-right-tablet">
                    <p class="is-size-7" style="color:#81c784">
                        DNS powered by <a href="https://desec.io" target="_blank" rel="noopener">deSEC.io</a>
                    </p>
                </div>
            </div>
        </footer>
    </div>
</section>

</body>
</html>
    <?php
}

// ── Mezzio Bootstrap ──────────────────────────────────────────────────────────
$container = require __DIR__ . '/app/container.php';

/** @var \Mezzio\Application $app */
$app     = $container->get(\Mezzio\Application::class);
$factory = $container->get(\Mezzio\MiddlewareFactory::class);

(require __DIR__ . '/app/pipeline.php')($app, $factory, $container);
(require __DIR__ . '/app/routes.php')($app, $factory, $container);

$app->run();

