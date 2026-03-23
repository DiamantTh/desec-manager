<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;
use Yosymfony\Toml\Toml;

/**
 * Loads and merges TOML configuration files into a unified PHP array.
 *
 * Load order (each level overrides the previous via array_replace_recursive):
 *   1. app.toml
 *   2. database.toml
 *   3. mail.toml
 *   4. security.toml
 *   5. config.local.toml (optional, gitignored — local overrides)
 *
 * Sensitive values (DB password, mail password, encryption key) are read from
 * environment variables: DB_PASSWORD, MAIL_PASSWORD, ENCRYPTION_KEY.
 */
final class TomlLoader
{
    /** @var list<string> Required configuration files (without .toml suffix) */
    private const FILES = ['app', 'database', 'mail', 'security'];

    public function __construct(private readonly string $configDir) {}

    /**
     * Loads all TOML configuration files and returns the merged array.
     *
     * @return array<string, mixed>
     * @throws RuntimeException if a required file is missing
     */
    public function load(): array
    {
        $merged = [];

        foreach (self::FILES as $name) {
            $path = $this->configDir . '/' . $name . '.toml';

            if (!file_exists($path)) {
                throw new RuntimeException(
                    "Konfigurationsdatei nicht gefunden: {$path}\n" .
                    "Tipp: Kopiere die entsprechende .dist-Datei und passe sie an."
                );
            }

            /** @var array<string, mixed> $data */
            $data   = Toml::parseFile($path);
            $merged = array_replace_recursive($merged, $data);
        }

        // Local overrides (gitignored — optional)
        $localPath = $this->configDir . '/config.local.toml';
        if (file_exists($localPath)) {
            /** @var array<string, mixed> $local */
            $local  = Toml::parseFile($localPath);
            $merged = array_replace_recursive($merged, $local);
        }

        // Apply secrets from environment variables (never commit secrets to TOML files)
        $merged = $this->applyEnvironmentSecrets($merged);

        return $merged;
    }

    /**
     * Overrides sensitive values with environment variables if set.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function applyEnvironmentSecrets(array $config): array
    {
        $dbPassword   = getenv('DB_PASSWORD');
        $mailPassword = getenv('MAIL_PASSWORD');
        $encKey       = getenv('ENCRYPTION_KEY');

        if ($dbPassword !== false && $dbPassword !== '') {
            $config['database']['password'] = $dbPassword;
        }

        if ($mailPassword !== false && $mailPassword !== '') {
            $config['mail']['smtp']['password'] = $mailPassword;
        }

        if ($encKey !== false && $encKey !== '') {
            $config['security']['encryption_key'] = $encKey;
        }

        return $config;
    }
}
