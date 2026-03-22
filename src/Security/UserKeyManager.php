<?php

declare(strict_types=1);

namespace App\Security;

/**
 * UserKeyManager — verwaltet den per-User-Encryption-Key im Session-Kontext.
 *
 * Konzept (ähnlich Keybase.io):
 *   Jeder Benutzer besitzt einen eigenen, zufälligen 256-Bit-Encryption-Key.
 *   Dieser Key liegt verschlüsselt ("wrapped") in der Datenbank.
 *   Der Schutzschlüssel (Wrapping-Key) wird aus dem Benutzerpasswort
 *   + einem zufälligen Salt via Argon2id abgeleitet — niemals gespeichert.
 *
 * Login-Ablauf:
 *   1. handleLogin():   Passwort korrekt → Key entschlüsseln → pending_enc_key
 *   2. (optional) MFA: TOTP- oder WebAuthn-Prüfung
 *   3. completeLogin(): pending_enc_key → enc_key (aktiv, bereit für Repositories)
 *
 * Passwortänderung:
 *   Der User-Key selbst ändert sich nicht — nur das "Wrapping" wird mit dem
 *   neuen Passwort erneuert. Alle deSEC API-Keys bleiben unverändert.
 *
 * Fallback:
 *   Wenn kein per-User-Key in der Session vorhanden ist (Legacy-Account ohne
 *   enc_key_wrapped in der DB), greifen Repositories auf den globalen App-Key
 *   zurück (EncryptionService-Standardverhalten ohne rawKey-Parameter).
 */
class UserKeyManager
{
    private const SESSION_KEY         = 'enc_key';
    private const SESSION_PENDING_KEY = 'pending_enc_key';

    public function __construct(private readonly EncryptionService $crypto)
    {
    }

    // -------------------------------------------------------------------------
    // User-Anlage
    // -------------------------------------------------------------------------

    /**
     * Erstellt beim Anlegen eines neuen Benutzers einen frischen User-Key
     * und verpackt (wrapped) ihn mit dem Benutzerpasswort.
     *
     * Rückgabe in UserRepository::updateWrappedKey() speichern.
     *
     * @return array{salt: string, wrapped_key: string}  Beide base64-kodiert.
     */
    public function initForNewUser(string $password): array
    {
        $salt        = EncryptionService::generateSalt();
        $rawUserKey  = EncryptionService::generateUserKey();
        $wrappingKey = $this->crypto->deriveKeyFromPassword($password, $salt);
        $wrapped     = $this->crypto->wrapKey($rawUserKey, $wrappingKey);

        sodium_memzero($rawUserKey);
        sodium_memzero($wrappingKey);

        return ['salt' => $salt, 'wrapped_key' => $wrapped];
    }

    // -------------------------------------------------------------------------
    // Login-Flow
    // -------------------------------------------------------------------------

    /**
     * Entschlüsselt den User-Key nach erfolgreicher Passwortprüfung und legt
     * ihn als `pending_enc_key` in der Session ab.
     *
     * Der Key verbleibt im Pending-Slot bis promoteToSession() aufgerufen wird
     * (nach dem MFA-Schritt, oder sofort bei Login ohne MFA).
     *
     * @param string $password   Eingegebenes Passwort (Klartext)
     * @param string $b64Salt    Base64-Salt aus users.enc_key_salt
     * @param string $b64Wrapped Base64-Wrapped-Key aus users.enc_key_wrapped
     * @throws \RuntimeException wenn der Key nicht entschlüsselt werden kann
     */
    public function unlockFromLogin(string $password, string $b64Salt, string $b64Wrapped): void
    {
        $wrappingKey = $this->crypto->deriveKeyFromPassword($password, $b64Salt);
        $rawUserKey  = $this->crypto->unwrapKey($b64Wrapped, $wrappingKey);

        sodium_memzero($wrappingKey);

        $_SESSION[self::SESSION_PENDING_KEY] = base64_encode($rawUserKey);

        sodium_memzero($rawUserKey);
    }

    /**
     * Verschiebt den `pending_enc_key` in den aktiven `enc_key`-Slot.
     * Wird am Ende des Login-Flows aufgerufen (nach MFA oder bei direktem Login).
     */
    public function promoteToSession(): void
    {
        if (isset($_SESSION[self::SESSION_PENDING_KEY])) {
            $_SESSION[self::SESSION_KEY] = $_SESSION[self::SESSION_PENDING_KEY];
            unset($_SESSION[self::SESSION_PENDING_KEY]);
        }
    }

    // -------------------------------------------------------------------------
    // Session-Zugriff (für Repositories)
    // -------------------------------------------------------------------------

    /**
     * Gibt den rohen 32-Byte-User-Key zurück, oder null wenn keiner verfügbar.
     * null → Repository fällt auf globalen App-Key zurück (Fallback-Verhalten).
     */
    public function getRawSessionKey(): ?string
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return null;
        }
        $raw = base64_decode((string) $_SESSION[self::SESSION_KEY], true);
        return ($raw !== false && strlen($raw) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
            ? $raw
            : null;
    }

    public function hasSessionKey(): bool
    {
        return $this->getRawSessionKey() !== null;
    }

    // -------------------------------------------------------------------------
    // Passwortänderung
    // -------------------------------------------------------------------------

    /**
     * Verpackt den aktuellen Session-User-Key mit dem neuen Passwort neu.
     * Nur das Wrapping ändert sich — alle verschlüsselten API-Keys bleiben gültig.
     *
     * Rückgabe in UserRepository::updateWrappedKey() speichern.
     *
     * @return array{salt: string, wrapped_key: string}  Beide base64-kodiert.
     * @throws \RuntimeException wenn kein aktiver Session-Key vorhanden ist
     */
    public function reWrapOnPasswordChange(string $newPassword): array
    {
        $rawKey = $this->getRawSessionKey();
        if ($rawKey === null) {
            throw new \RuntimeException('Kein aktiver Verschlüsselungsschlüssel in der Session.');
        }

        $newSalt     = EncryptionService::generateSalt();
        $wrappingKey = $this->crypto->deriveKeyFromPassword($newPassword, $newSalt);
        $wrapped     = $this->crypto->wrapKey($rawKey, $wrappingKey);

        sodium_memzero($wrappingKey);

        return ['salt' => $newSalt, 'wrapped_key' => $wrapped];
    }
}
