<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;
use Yosymfony\Toml\Toml;

/**
 * Lädt und mergt TOML-Konfigurationsdateien zu einem einheitlichen PHP-Array.
 *
 * Ladereihenfolge (jede Ebene überschreibt die vorherige via array_replace_recursive):
 *   1. app.toml
 *   2. database.toml
 *   3. mail.toml
 *   4. security.toml
 *   5. config.local.toml (optional, gitignored — lokale Überschreibungen)
 *
 * Sensible Werte (DB-Passwort, Mail-Passwort, Encryption-Key) werden aus
 * Umgebungsvariablen gelesen: DB_PASSWORD, MAIL_PASSWORD, ENCRYPTION_KEY.
 */
final class TomlLoader
{
    /** @var list<string> Pflicht-Konfigurationsdateien (ohne .toml-Suffix) */
    private const FILES = ['app', 'database', 'mail', 'security'];

    public function __construct(private readonly string $configDir) {}

    /**
     * Lädt alle TOML-Konfigurationsdateien und gibt das gemergete Array zurück.
     *
     * @return array<string, mixed>
     * @throws RuntimeException wenn eine Pflichtdatei fehlt
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

        // Lokale Überschreibungen (gitignored — optional)
        $localPath = $this->configDir . '/config.local.toml';
        if (file_exists($localPath)) {
            /** @var array<string, mixed> $local */
            $local  = Toml::parseFile($localPath);
            $merged = array_replace_recursive($merged, $local);
        }

        // Secrets aus Umgebungsvariablen einsetzen (niemals in TOML-Dateien committen)
        $merged = $this->applyEnvironmentSecrets($merged);

        return $merged;
    }

    /**
     * Überschreibt sensible Werte mit Umgebungsvariablen wenn gesetzt.
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
