<?php

declare(strict_types=1);

namespace App\Config;

use Yosymfony\Toml\Toml;

final class ConfigLoader
{
    private const DEFAULT_CONFIG_DIR = __DIR__ . '/../../config';
    private const SUPPORTED_FILES = [
        'config.php',
        'config.toml',
        'config.ini',
    ];

    private static ?array $cachedConfig = null;
    private static ?string $cachedDirectory = null;

    /**
     * @param string|null $directory Optional directory that contains the configuration file.
     * @return array<string, mixed>
     */
    public static function load(?string $directory = null): array
    {
        $directory = $directory ?? self::DEFAULT_CONFIG_DIR;
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);

        if (self::$cachedConfig !== null && self::$cachedDirectory === $directory) {
            return self::$cachedConfig;
        }

        foreach (self::SUPPORTED_FILES as $fileName) {
            $path = $directory . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($path)) {
                continue;
            }

            $config = self::parseFile($path);
            self::$cachedConfig = $config;
            self::$cachedDirectory = $directory;
            return $config;
        }

        throw new \RuntimeException(sprintf(
            'Keine Konfigurationsdatei gefunden. Erwartet wird eine der folgenden Dateien: %s',
            implode(', ', self::SUPPORTED_FILES)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseFile(string $path): array
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => self::parsePhp($path),
            'ini' => self::parseIni($path),
            'toml' => self::parseToml($path),
            default => throw new \RuntimeException(sprintf('Nicht unterstütztes Konfigurationsformat: %s', $path)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function parsePhp(string $path): array
    {
        /** @var mixed $config */
        $config = require $path;
        if (!is_array($config)) {
            throw new \RuntimeException(sprintf('Konfigurationsdatei %s muss ein Array zurückgeben.', $path));
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseIni(string $path): array
    {
        $config = parse_ini_file($path, true, INI_SCANNER_TYPED);
        if ($config === false) {
            throw new \RuntimeException(sprintf('Konfigurationsdatei %s konnte nicht gelesen werden.', $path));
        }

        return self::expandDotNotation($config);
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseToml(string $path): array
    {
        $config = Toml::ParseFile($path);
        if (!is_array($config)) {
            throw new \RuntimeException(sprintf('Konfigurationsdatei %s konnte nicht gelesen werden.', $path));
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function expandDotNotation(array $config): array
    {
        $result = [];

        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $value = self::expandDotNotation($value);
            }

            if (!is_string($key) || strpos($key, '.') === false) {
                $result = self::mergeRecursive($result, [$key => $value]);
                continue;
            }

            $segments = explode('.', $key);
            $current =& $result;
            $lastIndex = count($segments) - 1;

            foreach ($segments as $index => $segment) {
                if (!array_key_exists($segment, $current) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }

                if ($index === $lastIndex) {
                    $current[$segment] = $value;
                    unset($current);
                    continue 2;
                }

                $current =& $current[$segment];
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $merge
     * @return array<string, mixed>
     */
    private static function mergeRecursive(array $base, array $merge): array
    {
        foreach ($merge as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
