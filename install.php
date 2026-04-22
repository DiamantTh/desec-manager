<?php
/**
 * deSEC Manager – Installer (Legacy-Einstiegspunkt)
 *
 * Der Installer wurde in den Ordner install/ verschoben.
 * Diese Datei leitet automatisch weiter.
 *
 *   - CLI-Modus: install-cli.php wird direkt eingebunden
 *   - Web-Modus: Weiterleitung zu install/index.php
 */

declare(strict_types=1);

// ─── CLI ─────────────────────────────────────────────────────────────────────
if (PHP_SAPI === 'cli') {
    require __DIR__ . '/install-cli.php';
    exit;
}

// ─── Web: Weiterleitung zum neuen Installer-Ordner ───────────────────────────
header('Location: install/index.php', true, 301);
exit;
