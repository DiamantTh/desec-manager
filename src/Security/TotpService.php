<?php

declare(strict_types=1);

namespace App\Security;

use OTPHP\TOTP;

/**
 * TotpService — TOTP-Implementierung via spomky-labs/otphp v11.
 *
 * Konfiguration (aus security.toml → [security.totp]):
 *   digits       = 8       (statt RFC-Standard 6)
 *   algorithm    = sha256  (statt RFC-Standard sha1)
 *   period       = 30 s
 *   secret_bytes = 32      (256-Bit-Secret)
 *   window       = 1       (±1 Periode = ±30 s Toleranz)
 *
 * Workflow:
 *   1. $secret = $totp->generateSecret()           → in DB speichern, QR-Code zeigen
 *   2. $uri    = $totp->getProvisioningUri(...)    → für QR-Code (otpauth://)
 *   3. $ok     = $totp->verify($code, $secret)     → beim Login prüfen
 */
class TotpService
{
    private readonly int $digits;
    private readonly string $digest;
    private readonly int $period;
    private readonly int $secretBytes;
    private readonly int $window;

    /**
     * @param array<string, mixed> $config  Vollständige TOML-Konfiguration
     */
    public function __construct(array $config)
    {
        $cfg = $config['security']['totp'] ?? [];

        $this->digits      = max(6, (int)($cfg['digits']       ?? 8));
        $this->period      = max(1, (int)($cfg['period']       ?? 30));
        $this->secretBytes = max(16, (int)($cfg['secret_bytes'] ?? 32));
        $this->window      = max(0, (int)($cfg['window']       ?? 1));

        $digest = strtolower((string)($cfg['algorithm'] ?? 'sha256'));
        $this->digest = in_array($digest, ['sha1', 'sha256', 'sha512'], true) ? $digest : 'sha256';
    }

    /**
     * Erzeugt ein neues TOTP-Secret (Base32-kodiert, Uppercase).
     *
     * Das Secret muss sicher in der Datenbank gespeichert werden.
     * Es wird erst aktiviert, wenn der Nutzer einen gültigen Code eingibt (verify()).
     *
     * @return string  Base32-kodiertes Secret (z. B. "JBSWY3DPEHPK3PXP...")
     */
    public function generateSecret(): string
    {
        $totp = TOTP::create(
            secret:     null,
            period:     $this->period,
            digest:     $this->digest,
            digits:     $this->digits,
            secretSize: $this->secretBytes,
        );

        return $totp->getSecret();
    }

    /**
     * Liefert den otpauth://-URI für den QR-Code.
     *
     * Dieser URI wird in einen QR-Code umgewandelt, den der Nutzer
     * mit einer TOTP-App (Aegis, Google Authenticator, …) scannt.
     *
     * @param string $secret   Base32-kodiertes Secret (aus generateSecret())
     * @param string $label    Nutzername oder E-Mail-Adresse
     * @param string $issuer   Name der Anwendung (erscheint in der App)
     * @return string          otpauth://totp/…-URI
     */
    public function getProvisioningUri(string $secret, string $label, string $issuer): string
    {
        $totp = $this->buildTotp($secret);
        if ($label !== '') {
            $totp->setLabel($label);
        }
        if ($issuer !== '') {
            $totp->setIssuer($issuer);
        }

        return $totp->getProvisioningUri();
    }

    /**
     * Prüft ob ein eingegebener TOTP-Code korrekt ist.
     *
     * Das window-Parameter erlaubt ±N Perioden Toleranz für Uhrenabweichungen.
     * window=1 bedeutet: aktueller Code sowie je einen Code davor und danach akzeptieren.
     *
     * @param string   $code    Eingegebener Code (z. B. "12345678")
     * @param string   $secret  Base32-kodiertes Secret aus der Datenbank
     * @param int|null $timestamp  Vergleichszeitpunkt (null = jetzt)
     * @return bool
     */
    public function verify(string $code, string $secret, ?int $timestamp = null): bool
    {
        if ($code === '' || $secret === '') {
            return false;
        }

        $totp = $this->buildTotp($secret);

        $leeway = $this->window * $this->period;
        $ts     = ($timestamp !== null) ? max(0, $timestamp) : null;

        return $totp->verify($code, $ts, $leeway > 0 ? $leeway : null);
    }

    /**
     * Constructs a configured TOTP instance from a stored secret.
     */
    private function buildTotp(string $secret): TOTP
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('TOTP-Secret darf nicht leer sein.');
        }

        $totp = TOTP::createFromSecret($secret);
        $totp->setPeriod($this->period);
        $totp->setDigest($this->digest);
        $totp->setDigits($this->digits);

        return $totp;
    }
}
