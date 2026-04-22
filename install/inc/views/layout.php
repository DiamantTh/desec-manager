<?php

declare(strict_types=1);

/**
 * Installer – Layout-Wrapper
 *
 * Erwartet folgende Variablen aus dem aufrufenden Kontext:
 *   string $pageTitle        – <title>-Inhalt
 *   callable $renderContent  – gibt den Haupt-HTML-Inhalt aus
 *   bool   $showProgress     – Fortschrittsleiste anzeigen (default true)
 *   int    $displayStep      – aktueller Schritt (1–3, oder 4 = Erfolg)
 *   string[] $stepLabels     – Schritt-Bezeichnungen
 *   string[] $errors         – Fehlermeldungen (leer = keine)
 */

$locale      = $GLOBALS['_installer_locale'] ?? 'en';
$showProgress = $showProgress ?? true;
$errors       = $errors ?? [];
$stepLabels   = $stepLabels ?? [];
$displayStep  = $displayStep ?? 1;
?><!DOCTYPE html>
<html lang="<?= e($locale) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle ?? 'DeSEC Manager — ' . t('layout.title')) ?></title>
    <link rel="stylesheet" href="../../assets/css/bulma.min.css">
    <link rel="icon" type="image/svg+xml" href="../../assets/img/favicon.svg">
    <style>
        :root { --primary: #1976d2; --primary-dark: #1565c0; --success: #388e3c; color-scheme: light; }
        body { background: #f5f5f5; }
        .wizard-card { max-width: 820px; margin: 2rem auto; }
        .progress-bar { display: flex; gap: .5rem; margin-bottom: 2rem; }
        .progress-bar .pb-step {
            flex: 1; text-align: center; padding: .45rem .3rem;
            border-radius: 8px; font-size: .8rem; font-weight: 700;
            background: #e2e8f0; color: #64748b;
        }
        .progress-bar .pb-step.done   { background: #388e3c; color: #fff; }
        .progress-bar .pb-step.active { background: #1976d2; color: #fff; }
        .section-divider { border: none; border-top: 2px solid #e2e8f0; margin: 1.5rem 0; }
        .section-title { font-size: 1rem; font-weight: 700; color: #1976d2; margin-bottom: 1rem; }
        code { background: #f1f5f9; padding: .1em .3em; border-radius: 4px; font-size: .875em; }
        pre  { background: #f8fafc; border-radius: 6px; padding: .75rem 1rem; font-size: .85rem; }
        .warn-left { border-left: 4px solid #ff9800; padding-left: 1rem; }
        .tag-required { display: block; width: fit-content; margin-top: .25rem; }
        .lang-switcher { display: flex; flex-wrap: wrap; gap: .35rem; justify-content: flex-end; margin-bottom: .75rem; }
        .lang-switcher a {
            font-size: .72rem; padding: .15rem .45rem; border-radius: 4px;
            border: 1px solid #cbd5e1; color: #475569; text-decoration: none;
        }
        .lang-switcher a.active, .lang-switcher a:hover {
            background: #1976d2; border-color: #1976d2; color: #fff;
        }
    </style>
</head>
<body>
<div class="wizard-card card">
    <header class="card-header" style="background:linear-gradient(135deg,#1976d2,#0d47a1);border-radius:9px 9px 0 0">
        <p class="card-header-title" style="color:#fff;font-size:1.1rem">
            🛠️ DeSEC Manager — <?= e(t('layout.title')) ?>
        </p>
    </header>
    <div class="card-content">

        <?php /* ── Sprachauswahl ── */ ?>
        <div class="lang-switcher" aria-label="<?= e(t('layout.lang_switch')) ?>">
            <?php foreach (INSTALLER_LANGS as $code => $native):
                $active = $code === $locale ? ' active' : '';
                $url = 'index.php?lang=' . urlencode($code);
            ?>
            <a href="<?= e($url) ?>" class="<?= $active ?>" lang="<?= e($code) ?>"
               hreflang="<?= e($code) ?>"><?= e($native) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($showProgress): ?>
        <div class="progress-bar" role="progressbar"
             aria-valuenow="<?= $displayStep ?>" aria-valuemin="1" aria-valuemax="3">
            <?php foreach ($stepLabels as $i => $lbl):
                $n   = $i + 1;
                $cls = $n < $displayStep ? 'done' : ($n === $displayStep ? 'active' : '');
                $icon = $n < $displayStep ? '✓ ' : '';
            ?>
            <div class="pb-step <?= $cls ?>"><?= $icon . e($lbl) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="notification is-danger is-light mb-4" role="alert">
            <ul>
                <?php foreach ($errors as $err): ?>
                <li><?= $err ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php ($renderContent)(); ?>

    </div>
</div>
</body>
</html>
