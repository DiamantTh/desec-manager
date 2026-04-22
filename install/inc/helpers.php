<?php

declare(strict_types=1);

/**
 * Installer – gemeinsame Hilfsfunktionen
 */

/** HTML-sicheres Escapen */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Installer-Ordner rekursiv löschen */
function rmDirRecursive(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }
    $items = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir()
            ? rmdir((string) $item->getRealPath())
            : unlink((string) $item->getRealPath());
    }
    return rmdir($dir);
}

/**
 * Verfügbare App-Themes aus /themes/ einlesen.
 *
 * @return array<string, string>
 */
function getAvailableThemes(): array
{
    $themes = ['default' => 'Default', 'bulma' => 'Bulma Classic'];
    $td = PROJECT_ROOT . '/themes';
    if (is_dir($td)) {
        foreach ((array) scandir($td) as $entry) {
            if (
                is_string($entry)
                && $entry[0] !== '.'
                && is_dir($td . '/' . $entry)
                && !isset($themes[$entry])
            ) {
                $jf = $td . '/' . $entry . '/theme.json';
                $themes[$entry] = file_exists($jf)
                    ? (json_decode((string) file_get_contents($jf), true)['name'] ?? $entry)
                    : $entry;
            }
        }
    }
    return $themes;
}

/**
 * System-Anforderungen prüfen.
 *
 * @return array<string, array{ok: bool, label: string, detail: string, critical: bool}>
 */
function getRequirements(): array
{
    $c = [];

    $phpOk = version_compare(PHP_VERSION, '8.4.0') >= 0;
    $c['php'] = ['ok' => $phpOk, 'label' => t('req.php'), 'detail' => t('req.php_detail', PHP_VERSION), 'critical' => true];

    foreach (['pdo', 'sodium', 'openssl', 'json', 'mbstring'] as $ext) {
        $ok = extension_loaded($ext);
        $c[$ext] = [
            'ok'       => $ok,
            'label'    => t('req.ext', $ext),
            'detail'   => $ok ? t('req.loaded') : t('req.missing'),
            'critical' => true,
        ];
    }

    $hasMysql  = extension_loaded('pdo_mysql');
    $hasSqlite = extension_loaded('pdo_sqlite');
    $hasPgsql  = extension_loaded('pdo_pgsql');
    $c['pdo_db'] = [
        'ok'       => $hasMysql || $hasSqlite || $hasPgsql,
        'label'    => t('req.db_driver'),
        'detail'   => 'pdo_mysql: ' . ($hasMysql  ? '✓' : '✗')
                    . ' | pdo_pgsql: ' . ($hasPgsql  ? '✓' : '✗')
                    . ' | pdo_sqlite: ' . ($hasSqlite ? '✓' : '✗'),
        'critical' => true,
    ];

    $c['vendor'] = [
        'ok'       => VENDOR_OK,
        'label'    => t('req.vendor'),
        'detail'   => VENDOR_OK ? t('req.vendor_ok') : t('req.vendor_missing'),
        'critical' => true,
    ];

    $configDir = PROJECT_ROOT . '/config';
    $writable  = is_dir($configDir) ? is_writable($configDir) : is_writable(PROJECT_ROOT);
    $c['config_writable'] = [
        'ok'       => $writable,
        'label'    => t('req.config_write'),
        'detail'   => $writable ? t('req.ok') : t('req.no_write'),
        'critical' => true,
    ];

    $already = file_exists(PROJECT_ROOT . '/config/config.php');
    $c['already_installed'] = [
        'ok'       => true,
        'label'    => t('req.prev_install'),
        'detail'   => $already ? t('req.prev_install_detail') : t('req.no_prev_install'),
        'critical' => false,
    ];

    if (extension_loaded('sodium')) {
        if (defined('SODIUM_CRYPTO_AEAD_AEGIS256_KEYBYTES')) {
            $cipher = 'AEGIS-256 (libsodium ≥ 1.0.19, RFC 9826, AES-NI)';
        } elseif (defined('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES')) {
            $cipher = 'XChaCha20-Poly1305 IETF (libsodium ≥ 1.0.12)';
        } else {
            $cipher = 'XSalsa20-Poly1305 secretbox';
        }
        $cipherDetail = t('req.cipher_active', $cipher)
            . ' — libsodium ' . (defined('SODIUM_LIBRARY_VERSION') ? SODIUM_LIBRARY_VERSION : '?');
    } else {
        $cipherDetail = t('req.cipher_missing');
    }
    $c['cipher'] = [
        'ok'       => extension_loaded('sodium'),
        'label'    => t('req.cipher'),
        'detail'   => $cipherDetail,
        'critical' => false,
    ];

    return $c;
}
