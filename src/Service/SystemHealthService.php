<?php

namespace App\Service;

class SystemHealthService
{
    /**
     * @return array{opcache: array<string, mixed>, apcu: array<string, mixed>}
     */
    public function getCacheStatus(): array
    {
        return [
            'opcache' => $this->detectOpCache(),
            'apcu' => $this->detectApcu(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detectOpCache(): array
    {
        $extensionLoaded = extension_loaded('Zend OPcache');
        $sapi = PHP_SAPI;
        $isCli = strncmp($sapi, 'cli', 3) === 0;
        $settingKey = $isCli ? 'opcache.enable_cli' : 'opcache.enable';
        $settingValue = ini_get($settingKey);
        $enabled = $extensionLoaded && $this->toBool($settingValue);

        $status = null;
        $statusActive = false;
        $jitActive = false;
        if ($enabled && function_exists('opcache_get_status')) {
            try {
                $status = @opcache_get_status(false);
                if (is_array($status)) {
                    $statusActive = (bool) ($status['opcache_enabled'] ?? false);
                    $jitActive = (bool) (($status['jit'] ?? [])['enabled'] ?? false);
                }
            } catch (\Throwable $e) {
                $status = null;
            }
        }

        $message = null;
        if (!$extensionLoaded) {
            $message = 'OPcache Erweiterung ist nicht geladen. Aktivieren Sie sie in php.ini.';
        } elseif (!$this->toBool($settingValue)) {
            $directive = $settingKey;
            $message = sprintf('%s ist deaktiviert. Bitte auf "1" setzen, um OPcache zu nutzen.', $directive);
        } elseif (!$statusActive) {
            $message = 'OPcache ist geladen, aber meldet keinen aktiven Zustand. Prüfen Sie restriktive ini-Einstellungen.';
        }

        return [
            'extension_loaded' => $extensionLoaded,
            'enabled' => $enabled,
            'active' => $statusActive,
            'jit' => $jitActive,
            'message' => $message,
            'details' => [
                'sapi' => $sapi,
                'config_key' => $settingKey,
                'config_value' => $settingValue,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detectApcu(): array
    {
        $extensionLoaded = extension_loaded('apcu');
        $sapi = PHP_SAPI;
        $isCli = strncmp($sapi, 'cli', 3) === 0;
        $settingKey = $isCli ? 'apc.enable_cli' : 'apc.enabled';
        $settingValue = ini_get($settingKey);
        $enabled = $extensionLoaded && $this->toBool($settingValue);

        $infoAvailable = function_exists('apcu_cache_info');
        $cacheUp = false;
        if ($enabled && $infoAvailable) {
            try {
                $info = apcu_cache_info(false);
                $cacheUp = is_array($info);
            } catch (\Throwable $e) {
                $cacheUp = false;
            }
        }

        $message = null;
        if (!$extensionLoaded) {
            $message = 'APCu Erweiterung ist nicht geladen. Installieren und aktivieren Sie php-apcu, um object caching zu nutzen.';
        } elseif (!$this->toBool($settingValue)) {
            $message = sprintf('%s ist deaktiviert. Bitte auf "1" setzen, um APCu zu aktivieren.', $settingKey);
        } elseif (!$cacheUp) {
            $message = 'APCu ist geladen, liefert jedoch keine Cache-Informationen. Prüfen Sie Berechtigungen und shared memory Einstellungen.';
        }

        return [
            'extension_loaded' => $extensionLoaded,
            'enabled' => $enabled,
            'active' => $cacheUp,
            'message' => $message,
            'details' => [
                'sapi' => $sapi,
                'config_key' => $settingKey,
                'config_value' => $settingValue,
            ],
        ];
    }

    private function toBool(mixed $value): bool
    {
        if ($value === false) {
            return false;
        }
        if ($value === true) {
            return true;
        }
        $normalized = strtolower((string) $value);
        return in_array($normalized, ['1', 'on', 'yes', 'true'], true);
    }
}
