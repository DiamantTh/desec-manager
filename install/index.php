<?php

/**
 * DeSEC Manager – Web-Installer (install/index.php)
 *
 * SECURITY NOTE: Delete this directory after successful installation!
 *   rm -rf install/
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

// ── Erfolgreich installiert? ──────────────────────────────────────────────────
if (isset($_SESSION['install_result'])) {
    $pageTitle      = 'DeSEC Manager — ' . t('layout.title');
    $showProgress   = false;
    $displayStep    = 0;
    $stepLabels     = [];
    $errors         = [];
    $renderContent  = static function (): void {
        require __DIR__ . '/inc/views/success.php';
    };
    require __DIR__ . '/inc/views/layout.php';
    exit;
}

// ── Schritt ermitteln ────────────────────────────────────────────────────────
$step   = (int) ($_SESSION['install_step'] ?? 1);
$errors = [];
$action = '';

// ── POST-Verarbeitung ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Prüfung
    if (!isset($_POST['_csrf']) || $_POST['_csrf'] !== CSRF_TOKEN) {
        $errors[] = t('error.csrf');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'cleanup') {
            rmDirRecursive(INSTALL_DIR);
            header('Location: ../index.php');
            exit;
        } elseif ($action === 'install') {
            require __DIR__ . '/inc/step3.php';
            $errors = processStep3();
            if (empty($errors)) {
                header('Location: index.php');
                exit;
            }
            $step = 3;
        } else {
            require __DIR__ . '/inc/step' . min(max($step, 1), 3) . '.php';
            $errors = match ($step) {
                1 => processStep1(),
                2 => processStep2(),
                default => [],
            };
            if (empty($errors)) {
                header('Location: index.php');
                exit;
            }
        }
    }
}

// ── Schritt-Dateien laden (GET) ──────────────────────────────────────────────
$safeStep = min(max($step, 1), 3);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/inc/step' . $safeStep . '.php';
}

// ── View-Daten vorbereiten ───────────────────────────────────────────────────
$stepLabels    = [t('steps.s1'), t('steps.s2'), t('steps.s3')];
$displayStep   = $step;
$showProgress  = true;
$pageTitle     = 'DeSEC Manager — ' . t('layout.title');

$reqs          = getRequirements();
$themes        = getAvailableThemes();
$allCriticalOk = array_reduce(
    $reqs,
    static fn (bool $carry, array $r): bool => $carry && (!$r['critical'] || $r['ok']),
    true
);

$renderContent = match ($safeStep) {
    1 => static function () use ($reqs, $allCriticalOk): void {
        renderStep1($reqs, $allCriticalOk);
    },
    2 => static function () use ($themes): void {
        renderStep2($themes);
    },
    3 => static function (): void {
        renderStep3();
    },
};

require __DIR__ . '/inc/views/layout.php';
