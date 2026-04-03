<?php

declare(strict_types=1);

namespace App\Security;

use Webauthn\PublicKeyCredentialSource;

/**
 * Ergebnis einer erfolgreichen WebAuthn-Authentifizierung.
 *
 * Kapselt den aktualisierten Credential-Source sowie das UV-Flag, das aussagt,
 * ob der Nutzer am Authenticator selbst verifiziert wurde (PIN, Fingerabdruck,
 * Face ID etc.) — Bit 2 der Authenticator-Data-Flags gemäß WebAuthn-Spec.
 *
 * @see https://www.w3.org/TR/webauthn/#flags
 */
readonly class AuthenticationResult
{
    public function __construct(
        /** Aktualisierter Credential-Source (sign_count, backup_state) */
        public PublicKeyCredentialSource $source,
        /**
         * UV-Flag: true = User Verification wurde durchgeführt
         * (PIN-Eingabe oder Biometrie direkt am Authenticator).
         * Bei Platform-Authenticatoren (Face ID, Windows Hello …) ist UV
         * immer true. Bei Hardware-Keys (USB/NFC/BLE) hängt es davon ab,
         * ob ein PIN gesetzt und abgefragt wurde.
         */
        public bool $uvPerformed,
    ) {}
}
