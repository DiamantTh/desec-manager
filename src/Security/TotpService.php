<?php

declare(strict_types=1);

namespace App\Security;

use OTPHP\TOTP;

/**
 * TotpService — TOTP implementation via spomky-labs/otphp v11.
 *
 * Configuration (from security.toml → [security.totp]):
 *   digits       = 8       (instead of RFC default 6)
 *   algorithm    = sha256  (instead of RFC default sha1)
 *   period       = 30 s
 *   secret_bytes = 32      (256-bit secret)
 *   window       = 1       (±1 period = ±30 s tolerance)
 *
 * Workflow:
 *   1. $secret = $totp->generateSecret()           → store in DB, show QR code
 *   2. $uri    = $totp->getProvisioningUri(...)    → for QR code (otpauth://)
 *   3. $ok     = $totp->verify($code, $secret)     → verify at login
 */
class TotpService
{
    private readonly int    $digits;
    private readonly string $digest;
    private readonly int    $period;
    private readonly int    $secretBytes;
    private readonly int    $window;

    /**
     * @param array<string, mixed> $config  Full TOML configuration array
     */
    public function __construct(array $config)
    {
        $cfg = $config['security']['totp'] ?? [];

        $this->digits      = max(6,  (int) ($cfg['digits']       ?? 8));
        $this->period      = max(1,  (int) ($cfg['period']       ?? 30));
        $this->secretBytes = max(16, (int) ($cfg['secret_bytes'] ?? 32));
        $this->window      = max(0,  (int) ($cfg['window']       ?? 1));

        $digest      = strtolower((string) ($cfg['algorithm'] ?? 'sha256'));
        $this->digest = in_array($digest, ['sha1', 'sha256', 'sha512'], true) ? $digest : 'sha256';
    }

    /**
     * Generates a new TOTP secret (Base32-encoded, uppercase).
     *
     * The secret must be stored securely in the database.
     * It is only activated once the user confirms with a valid code (verify()).
     *
     * @return string  Base32-encoded secret (e.g. "JBSWY3DPEHPK3PXP…")
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
     * Returns the otpauth:// URI for the QR code.
     *
     * This URI is converted into a QR code that the user scans
     * with a TOTP app (Aegis, Google Authenticator, …).
     *
     * @param string $secret   Base32-encoded secret (from generateSecret())
     * @param string $label    Username or email address
     * @param string $issuer   Application name (shown in the authenticator app)
     * @return string          otpauth://totp/… URI
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
     * Checks whether an entered TOTP code is correct.
     *
     * The window parameter allows ±N period tolerance for clock skew.
     * window=1 means: accept the current code as well as one before and after.
     *
     * @param string   $code       Entered code (e.g. "12345678")
     * @param string   $secret     Base32-encoded secret from the database
     * @param int|null $timestamp  Comparison timestamp (null = now)
     */
    public function verify(string $code, string $secret, ?int $timestamp = null): bool
    {
        if ($code === '' || $secret === '') {
            return false;
        }

        $totp   = $this->buildTotp($secret);
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
            throw new \InvalidArgumentException('TOTP secret must not be empty.');
        }

        $totp = TOTP::createFromSecret($secret);
        $totp->setPeriod($this->period);
        $totp->setDigest($this->digest);
        $totp->setDigits($this->digits);

        return $totp;
    }
}
