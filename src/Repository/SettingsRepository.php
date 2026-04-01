<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * SettingsRepository — liest und schreibt Laufzeit-Einstellungen aus der DB.
 *
 * Einstellungen sind einfache Key-Value-Paare (Tabelle: settings).
 * Schlüssel-Konvention: "gruppe.name", z.B. "mail.from_address".
 *
 * Typen: string | int | bool | json
 * Typisierter Lesezugriff via get(), getString(), getInt(), getBool(), getJson().
 *
 * Einstellungen die sicherheitskritisch sind (encryption_key, session-Flags)
 * sind NICHT in der DB — diese stehen fest in config.toml.
 */
class SettingsRepository
{
    /** @var array<string, string>|null In-Memory-Cache für den aktuellen Request */
    private ?array $cache = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    // -------------------------------------------------------------------------
    // Lesen
    // -------------------------------------------------------------------------

    /**
     * Liest einen Einstellungswert als rohen String.
     * Gibt $default zurück wenn der Schlüssel nicht existiert.
     */
    public function get(string $key, string $default = ''): string
    {
        return $this->all()[$key] ?? $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        return $this->get($key, $default);
    }

    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->get($key);
        return $val !== '' ? (int) $val : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $val = $this->get($key);
        if ($val === '') {
            return $default;
        }
        return in_array(strtolower($val), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array<mixed>|null null wenn Schlüssel fehlt oder ungültiges JSON
     */
    public function getJson(string $key): ?array
    {
        $val = $this->get($key);
        if ($val === '') {
            return null;
        }
        $decoded = json_decode($val, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Gibt alle Einstellungen als Key→Value-Map zurück (gecacht pro Request).
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $rows = $this->connection->fetchAllAssociative('SELECT "key", "value" FROM settings');
        $this->cache = [];
        foreach ($rows as $row) {
            $this->cache[(string) $row['key']] = (string) $row['value'];
        }

        return $this->cache;
    }

    // -------------------------------------------------------------------------
    // Schreiben
    // -------------------------------------------------------------------------

    /**
     * Setzt einen Einstellungswert (Upsert — INSERT OR UPDATE).
     */
    public function set(string $key, string $value): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // Plattformunabhängiges Upsert via Doctrine DBAL
        $existing = $this->connection->fetchOne(
            'SELECT "key" FROM settings WHERE "key" = ?',
            [$key]
        );

        if ($existing !== false) {
            $this->connection->update(
                'settings',
                ['value' => $value, 'updated_at' => $now],
                ['key'   => $key]
            );
        } else {
            $this->connection->insert('settings', [
                'key'        => $key,
                'value'      => $value,
                'updated_at' => $now,
            ]);
        }

        // Cache invalidieren
        $this->cache = null;
    }

    /**
     * Setzt mehrere Einstellungen in einer Transaktion.
     *
     * @param array<string, string> $values key => value
     */
    public function setMany(array $values): void
    {
        $this->connection->transactional(function () use ($values): void {
            foreach ($values as $key => $value) {
                $this->set((string) $key, (string) $value);
            }
        });
    }

    /**
     * Cache-Invalidierung — z.B. nach Admin-Aktionen aufrufen.
     */
    public function invalidateCache(): void
    {
        $this->cache = null;
    }
}
