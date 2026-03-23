<?php

declare(strict_types=1);

namespace App\Security;

/**
 * EncryptionService — symmetrische Verschlüsselung mit libsodium.
 *
 * Cipher-Hierarchie (zur Laufzeit geprüft, bester verfügbarer Cipher wird gewählt):
 *
 *   Stufe 3 — AEGIS-256            (libsodium ≥ 1.0.19, z. B. Debian 13 / Ubuntu 24.04+)
 *     Präfix 0x03 | 32-Byte-Key | 32-Byte-Nonce | AES-NI-beschleunigt | RFC 9826
 *
 *   Stufe 2 — XChaCha20-Poly1305  (libsodium ≥ 1.0.12, z. B. Debian 12 mit 1.0.18)
 *     Präfix 0x02 | 32-Byte-Key | 24-Byte-Nonce | software-constant-time
 *
 *   Stufe 1 — XSalsa20-Poly1305   (secretbox, libsodium ≥ 1.0.0, letzter Ausweg)
 *     Präfix 0x01 | 32-Byte-Key | 24-Byte-Nonce
 *
 *   Legacy  — XSalsa20-Poly1305   (kein Präfix-Byte, Daten vor der Versionierung)
 *
 * Da alle Stufen 32-Byte-Keys verwenden, sind bestehende Schlüssel
 * direkt wiederverwendbar — keine Key-Migration notwendig.
 *
 * Schlüssel-Parameter:
 *   - Ohne $rawKey: globaler App-Schlüssel aus der TOML-Konfiguration.
 *   - Mit $rawKey:  expliziter 32-Byte-Rohschlüssel (z. B. per-User-Session-Key).
 *
 * Key-Ableitung (Login):
 *   Aus dem Benutzerpasswort wird über Argon2id ein Wrapping-Key abgeleitet,
 *   mit dem der User-Encryption-Key geschützt ("wrapped") in der DB liegt.
 *   Beim Login wird er entschlüsselt und (über UserKeyManager) in der Session gehalten.
 */
class EncryptionService
{
    /** Ciphertext-Versions-Präfix-Bytes — aufsteigend = neuerer Cipher */
    private const V3_AEGIS256   = "\x03"; // AEGIS-256 (libsodium ≥ 1.0.19)
    private const V2_XCHACHA20  = "\x02"; // XChaCha20-Poly1305 IETF (libsodium ≥ 1.0.12)
    private const V1_SECRETBOX  = "\x01"; // XSalsa20-Poly1305 versioned (legacy)

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
     * Verschlüsselt $data authentifiziert.
     *
     * Wählt zur Laufzeit den besten verfügbaren Cipher:
     *   AEGIS-256 (libsodium ≥ 1.0.19) → XChaCha20-Poly1305 (libsodium ≥ 1.0.12) → secretbox.
     * Das Ergebnis trägt ein 1-Byte-Versions-Präfix für transparente Multi-Cipher-Entschlüsselung.
     *
     * @param string      $data    Klartext
     * @param string|null $rawKey  Optionaler 32-Byte-Rohschlüssel; null = App-Schlüssel
     * @param string      $ad      Additional Authenticated Data (mitauthentifiziert, nicht verschlüsselt)
     * @return string  Base64-kodierter Ciphertext (Version[1] || Nonce || Ciphertext)
     */
    public function encrypt(string $data, ?string $rawKey = null, string $ad = ''): string
    {
        $key = $rawKey ?? $this->appRawKey;

        if (defined('SODIUM_CRYPTO_AEAD_AEGIS256_KEYBYTES')) {
            // Stufe 3: AEGIS-256 — libsodium ≥ 1.0.19, AES-NI, RFC 9826
            $nonce  = random_bytes(SODIUM_CRYPTO_AEAD_AEGIS256_NPUBBYTES); // 32 Byte
            $cipher = sodium_crypto_aead_aegis256_encrypt($data, $ad, $nonce, $key);
            sodium_memzero($data);
            return base64_encode(self::V3_AEGIS256 . $nonce . $cipher);
        }

        if (defined('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES')) {
            // Stufe 2: XChaCha20-Poly1305 — libsodium ≥ 1.0.12 (z. B. Debian 12)
            $nonce  = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES); // 24 Byte
            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($data, $ad, $nonce, $key);
            sodium_memzero($data);
            return base64_encode(self::V2_XCHACHA20 . $nonce . $cipher);
        }

        // Stufe 1: XSalsa20-Poly1305 (secretbox) — letzter Ausweg
        $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); // 24 Byte
        $cipher = sodium_crypto_secretbox($data, $nonce, $key);
        sodium_memzero($data);
        return base64_encode(self::V1_SECRETBOX . $nonce . $cipher);
    }

    /**
     * Entschlüsselt und verifiziert einen mit encrypt() erzeugten Wert.
     *
     * Unterstützt alle vier Ciphertext-Formate (rückwärtskompatibel):
     *   0x03 = AEGIS-256              (libsodium ≥ 1.0.19)
     *   0x02 = XChaCha20-Poly1305    (libsodium ≥ 1.0.12)
     *   0x01 = XSalsa20-Poly1305 versioned
     *   kein Präfix = XSalsa20-Poly1305 unversioned (sehr alte Daten)
     *
     * @param string      $encoded  Base64-kodierter Ciphertext
     * @param string|null $rawKey   Optionaler 32-Byte-Rohschlüssel; null = App-Schlüssel
     * @param string      $ad       AAD (muss identisch mit dem beim Verschlüsseln verwendeten sein)
     * @throws \RuntimeException bei manipulierten Daten, falschem Schlüssel oder fehlendem Cipher
     */
    public function decrypt(string $encoded, ?string $rawKey = null, string $ad = ''): string
    {
        $key     = $rawKey ?? $this->appRawKey;
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || strlen($decoded) < 2) {
            throw new \RuntimeException('Decryption failed: invalid ciphertext.');
        }

        $version = $decoded[0];

        if ($version === self::V3_AEGIS256) {
            // AEGIS-256 (libsodium ≥ 1.0.19)
            if (!defined('SODIUM_CRYPTO_AEAD_AEGIS256_KEYBYTES')) {
                throw new \RuntimeException('AEGIS-256 nicht verfügbar — libsodium ≥ 1.0.19 benötigt.');
            }
            $nonceLen = SODIUM_CRYPTO_AEAD_AEGIS256_NPUBBYTES; // 32
            if (strlen($decoded) <= 1 + $nonceLen) {
                throw new \RuntimeException('Decryption failed: AEGIS-256-Ciphertext zu kurz.');
            }
            $nonce  = substr($decoded, 1, $nonceLen);
            $cipher = substr($decoded, 1 + $nonceLen);
            $plain  = sodium_crypto_aead_aegis256_decrypt($cipher, $ad, $nonce, $key);
            if ($plain === false) {
                throw new \RuntimeException('Decryption failed: authentication tag mismatch (AEGIS-256).');
            }
        } elseif ($version === self::V2_XCHACHA20) {
            // XChaCha20-Poly1305 IETF (libsodium ≥ 1.0.12)
            $nonceLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES; // 24
            if (strlen($decoded) <= 1 + $nonceLen) {
                throw new \RuntimeException('Decryption failed: XChaCha20-Ciphertext zu kurz.');
            }
            $nonce  = substr($decoded, 1, $nonceLen);
            $cipher = substr($decoded, 1 + $nonceLen);
            $plain  = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, $ad, $nonce, $key);
            if ($plain === false) {
                throw new \RuntimeException('Decryption failed: authentication tag mismatch (XChaCha20).');
            }
        } elseif ($version === self::V1_SECRETBOX) {
            // XSalsa20-Poly1305 versioned (legacy mit Präfix)
            $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES; // 24
            if (strlen($decoded) <= 1 + $nonceLen) {
                throw new \RuntimeException('Decryption failed: invalid ciphertext.');
            }
            $nonce  = substr($decoded, 1, $nonceLen);
            $cipher = substr($decoded, 1 + $nonceLen);
            $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            if ($plain === false) {
                throw new \RuntimeException('Decryption failed: authentication tag mismatch (secretbox).');
            }
        } else {
            // Legacy: unversioned XSalsa20-Poly1305 (Daten ohne Versionspräfix)
            $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES; // 24
            if (strlen($decoded) <= $nonceLen) {
                throw new \RuntimeException('Decryption failed: invalid ciphertext.');
            }
            $nonce  = substr($decoded, 0, $nonceLen);
            $cipher = substr($decoded, $nonceLen);
            $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            if ($plain === false) {
                throw new \RuntimeException('Decryption failed: authentication tag mismatch.');
            }
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
     * Alle Cipher-Stufen nutzen 32-Byte-Keys — der generierte Schlüssel ist universell.
     */
    public static function generateKey(): string
    {
        if (defined('SODIUM_CRYPTO_AEAD_AEGIS256_KEYBYTES')) {
            return base64_encode(sodium_crypto_aead_aegis256_keygen()); // 32 Byte
        }
        if (defined('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES')) {
            return base64_encode(sodium_crypto_aead_xchacha20poly1305_ietf_keygen()); // 32 Byte
        }
        return base64_encode(sodium_crypto_secretbox_keygen()); // 32 Byte
    }
}

