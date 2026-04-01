<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;
use Yosymfony\Toml\Toml;

/**
 * Loads and merges TOML configuration files into a unified PHP array.
 *
 * Load order (each level overrides the previous via array_replace_recursive):
 *   1. config.toml   — app, cache, mail, security (alles außer DB-Verbindung)
 *   2. database.toml — DB-Verbindungsparameter (separat, da vor DB-Start benötigt)
 *   3. config.local.toml (optional, gitignored — lokale Überschreibungen, Secrets)
 *
 * Legacyunterstützung: Existieren noch app.toml/mail.toml/security.toml, werden
 * sie vor config.local.toml eingemischt, sodass der Übergang ohne Downtime funktioniert.
 *
 * Sensitive values (DB password, mail password, encryption key) are read from
 * environment variables: DB_PASSWORD, MAIL_PASSWORD, ENCRYPTION_KEY, SENTRY_DSN.
 */
final class TomlLoader
{
    /** @var list<string> Pflicht-Konfigurationsdateien (ohne .toml-Suffix) */
    private const REQUIRED = ['config', 'database'];

    /** @var list<string> Legacy-Dateien — werden eingemischt wenn vorhanden */
    private const LEGACY = ['app', 'mail', 'security'];

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

        // 1 + 2. Pflichtdateien laden
        foreach (self::REQUIRED as $name) {
            $path = $this->configDir . '/' . $name . '.toml';

            if (!file_exists($path)) {
                throw new RuntimeException(
                    "Konfigurationsdatei nicht gefunden: {$path}\n" .
                    "Tipp: Kopiere config/config.toml.dist → config/config.toml und passe sie an."
                );
            }

            /** @var array<string, mixed> $data */
            $data   = Toml::parseFile($path);
            $merged = array_replace_recursive($merged, $data);
        }

        // Legacy-Dateien (app.toml / mail.toml / security.toml) wenn noch vorhanden
        foreach (self::LEGACY as $name) {
            $path = $this->configDir . '/' . $name . '.toml';
            if (file_exists($path)) {
                /** @var array<string, mixed> $data */
                $data   = Toml::parseFile($path);
                $merged = array_replace_recursive($merged, $data);
            }
        }

        // 3. Lokale Überschreibungen (gitignored — optional)
        $localPath = $this->configDir . '/config.local.toml';
        if (file_exists($localPath)) {
            /** @var array<string, mixed> $local */
            $local  = Toml::parseFile($localPath);
            $merged = array_replace_recursive($merged, $local);
        }

        // Secrets aus Umgebungsvariablen überschreiben TOML-Werte
        $merged = $this->applyEnvironmentSecrets($merged);

        return $merged;
    }

    /**
     * Überschreibt sicherheitskritische Werte mit Umgebungsvariablen.
     * Umgebungsvariablen haben immer Vorrang vor TOML-Werten.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function applyEnvironmentSecrets(array $config): array
    {
        $map = [
            'DB_PASSWORD'   => ['database', 'password'],
            'MAIL_PASSWORD' => ['mail', 'smtp', 'password'],
            'ENCRYPTION_KEY' => ['security', 'encryption_key'],
            'SENTRY_DSN'    => ['sentry', 'dsn'],
        ];

        foreach ($map as $envVar => $path) {
            $value = getenv($envVar);
            if ($value !== false && $value !== '') {
                $ref = &$config;
                foreach ($path as $i => $key) {
                    if ($i === count($path) - 1) {
                        $ref[$key] = $value;
                    } else {
                        if (!isset($ref[$key]) || !is_array($ref[$key])) {
                            $ref[$key] = [];
                        }
                        $ref = &$ref[$key];
                    }
                }
                unset($ref);
            }
        }

        return $config;
    }
}
