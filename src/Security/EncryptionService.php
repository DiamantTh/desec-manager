<?php

declare(strict_types=1);

namespace App\Security;

/**
 * EncryptionService — symmetrische Verschlüsselung mit libsodium.
 *
 * Verwendet XSalsa20-Poly1305 (authenticated encryption).
 * Jeder Aufruf von encrypt() wählt eine neue, zufällige 24-Byte-Nonce —
 * gleiche Daten mit gleichem Schlüssel liefern immer unterschiedliche Ciphertexte.
 *
 * Schlüssel-Parameter:
 *   - Ohne $rawKey: globaler App-Schlüssel aus der TOML-Konfiguration (Fallback).
 *   - Mit $rawKey:  expliziter 32-Byte-Rohschlüssel (z. B. per-User-Session-Key).
 *
 * Key-Ableitung (Login):
 *   Aus dem Benutzerpasswort wird über Argon2id ein Wrapping-Key abgeleitet,
 *   mit dem der User-Encryption-Key geschützt ("wrapped") in der DB liegt.
 *   Beim Login wird er entschlüsselt und (über UserKeyManager) in der Session gehalten.
 */
class EncryptionService
{
    /** Rohschlüssel (32 Byte) des globalen App-Encryption-Keys */
    private readonly string $appRawKey;

    /**
     * @param string $b64AppKey  Base64-kodierter 32-Byte-App-Schlüssel aus der Konfiguration.
     *                           Über ENCRYPTION_KEY-Umgebungsvariable oder config.local.toml setzen.
     */
    public function __construct(string $b64AppKey)
    {
        if ($b64AppKey === '') {
            throw new \RuntimeException('Encryption key not configured.');
        }
        $raw = base64_decode($b64AppKey, true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException(
                'Encryption key must be exactly 32 bytes. Use EncryptionService::generateKey() to create one.'
            );
        }
        $this->appRawKey = $raw;
    }

    // -------------------------------------------------------------------------
    // Verschlüsselung / Entschlüsselung
    // -------------------------------------------------------------------------

    /**
     * Verschlüsselt $data authentifiziert mit XSalsa20-Poly1305.
     *
     * @param string      $data    Klartext
     * @param string|null $rawKey  Optionaler 32-Byte-Rohschlüssel; null = App-Schlüssel
     * @return string  Base64-kodierter Ciphertext (Nonce || Ciphertext)
     */
    public function encrypt(string $data, ?string $rawKey = null): string
    {
        $key    = $rawKey ?? $this->appRawKey;
        $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($data, $nonce, $key);
        sodium_memzero($data);
        return base64_encode($nonce . $cipher);
    }

    /**
     * Entschlüsselt und verifiziert einen mit encrypt() erzeugten Wert.
     *
     * @param string      $encoded  Base64-kodierter Ciphertext (Nonce || Ciphertext)
     * @param string|null $rawKey   Optionaler 32-Byte-Rohschlüssel; null = App-Schlüssel
     * @throws \RuntimeException bei manipulierten Daten oder falschem Schlüssel
     */
    public function decrypt(string $encoded, ?string $rawKey = null): string
    {
        $key     = $rawKey ?? $this->appRawKey;
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Decryption failed: invalid ciphertext.');
        }

        $nonce  = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $key);

        if ($plain === false) {
            throw new \RuntimeException('Decryption failed: authentication tag mismatch.');
        }

        $result = $plain;
        sodium_memzero($plain);
        return $result;
    }

    // -------------------------------------------------------------------------
    // Key-Ableitung aus Passwort (für User-Key-Wrapping)
    // -------------------------------------------------------------------------

    /**
     * Leitet einen 32-Byte-Wrapping-Key aus einem Passwort + Salt ab (Argon2id).
     *
     * @param  string $password  Benutzerpasswort (Klartext)
     * @param  string $b64Salt   Base64-kodierter 16-Byte-Salt (aus generateSalt())
     * @return string            32 Byte Rohschlüssel (nicht base64-kodiert)
     */
    public function deriveKeyFromPassword(string $password, string $b64Salt): string
    {
        $salt = base64_decode($b64Salt, true);
        if ($salt === false || strlen($salt) !== SODIUM_CRYPTO_PWHASH_SALTBYTES) {
            throw new \RuntimeException('Invalid salt for key derivation.');
        }
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $password,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
        );
    }

    /**
     * Erzeugt einen zufälligen 16-Byte-Salt und gibt ihn base64-kodiert zurück.
     */
    public static function generateSalt(): string
    {
        return base64_encode(random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES));
    }

    // -------------------------------------------------------------------------
    // User-Key-Wrapping
    // -------------------------------------------------------------------------

    /**
     * Verpackt (wrapped) den User-Key mit einem Wrapping-Key.
     *
     * @param  string $rawUserKey     32-Byte-User-Key (Rohbytes, aus generateUserKey())
     * @param  string $rawWrappingKey 32-Byte-Wrapping-Key (aus deriveKeyFromPassword())
     * @return string                 Base64-kodierter Wrapped-Key (Nonce || Ciphertext)
     */
    public function wrapKey(string $rawUserKey, string $rawWrappingKey): string
    {
        return $this->encrypt($rawUserKey, $rawWrappingKey);
    }

    /**
     * Entpackt (unwrapped) den User-Key mit dem Wrapping-Key.
     *
     * @param  string $b64Wrapped     Base64-kodierter Wrapped-Key (aus wrapKey())
     * @param  string $rawWrappingKey 32-Byte-Wrapping-Key
     * @return string                 32-Byte-User-Key (Rohbytes)
     * @throws \RuntimeException wenn der Wrapping-Key falsch ist (falsches Passwort)
     */
    public function unwrapKey(string $b64Wrapped, string $rawWrappingKey): string
    {
        return $this->decrypt($b64Wrapped, $rawWrappingKey);
    }

    // -------------------------------------------------------------------------
    // Schlüssel-Generierung
    // -------------------------------------------------------------------------

    /**
     * Erzeugt einen neuen zufälligen 32-Byte-User-Encryption-Key (Rohbytes).
     * Wird bei der User-Anlage einmalig aufgerufen.
     */
    public static function generateUserKey(): string
    {
        return sodium_crypto_secretbox_keygen();
    }

    /**
     * Erzeugt für install.php / CLI einen neuen App-Encryption-Key (base64-kodiert).
     * Dieser Schlüssel wird einmalig generiert und in die Konfiguration eingetragen.
     */
    public static function generateKey(): string
    {
        return base64_encode(sodium_crypto_secretbox_keygen());
    }
}

