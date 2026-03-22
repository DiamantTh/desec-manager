<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * DecryptedApiKey — kurzlebiges Value-Object für entschlüsselte deSEC API-Keys.
 *
 * Der Klartext-Key verbleibt nie länger als nötig im Speicher.
 * Nach Verwendung (oder spätestens im Destruktor) wird er per sodium_memzero()
 * aus dem RAM gelöscht.
 *
 * Verwendung:
 *   $key = $apiKeyRepo->findDecryptedById($id, $userId);
 *   $client = new DeSECClient($key->getApiKey());
 *   unset($key); // → Destruktor → sodium_memzero
 */
final class DecryptedApiKey
{
    /** @var string Klartext-Key (wird im Destruktor per sodium_memzero() genullt) */
    private string $apiKey;

    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $name,
        string $apiKey,
    ) {
        $this->apiKey = $apiKey;
    }

    /**
     * Gibt den Klartext-Key zurück.
     * Den Rückgabewert nur kurzzeitig benutzen — niemals persistent speichern!
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Löscht den Klartext-Key sicher aus dem RAM.
     */
    public function __destruct()
    {
        if (function_exists('sodium_memzero')) {
            // PHPStan: sodium_memzero() ändert den Wert im Speicher, setzt ihn nicht auf null
            // @phpstan-ignore assign.propertyType
            sodium_memzero($this->apiKey);
        }
    }
}
