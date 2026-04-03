<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * SessionRepository — verwaltet die user_sessions-Tabelle.
 *
 * Beim Login wird eine Session-Record mit einem 32-Byte-Token angelegt,
 * das Token in der PHP-Session gespeichert, und auf jede authentifizierte
 * Anfrage gegen die Datenbank geprüft.
 *
 * Dadurch kann ein Admin aktive Sessions im Panel sehen und bei Bedarf
 * per Klick invalidieren — ohne direkten Zugriff auf Session-Dateien.
 */
class SessionRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Legt beim Login einen neuen Session-Record an.
     *
     * @param int    $userId       ID des eingeloggten Benutzers
     * @param string $username     Benutzername (denormalisiert, bleibt auch nach User-Löschung)
     * @param string $sessionToken Zufälliger Token (wird in der PHP-Session gespeichert)
     * @param bool   $isTls        War die Verbindung beim Login TLS-gesichert?
     * @param bool   $mfaUsed      Wurde 2FA (TOTP oder WebAuthn) verwendet?
     * @param string $clientIp     IP-Adresse des Clients
     * @param string $userAgent    User-Agent-Header des Browsers
     * @param int    $lifetime     Session-Lebensdauer in Sekunden
     */
    public function create(
        int $userId,
        string $username,
        string $sessionToken,
        bool $isTls,
        bool $mfaUsed,
        string $clientIp,
        string $userAgent,
        int $lifetime,
    ): void {
        $now        = $this->clock->now();
        $validUntil = (clone $now)->modify("+{$lifetime} seconds");

        $this->connection->insert('user_sessions', [
            'session_token' => $sessionToken,
            'user_id'       => $userId,
            'username'      => $username,
            'is_valid'      => 1,
            'is_tls'        => $isTls ? 1 : 0,
            'mfa_used'      => $mfaUsed ? 1 : 0,
            'login_at'      => $now->format('Y-m-d H:i:s'),
            'valid_until'   => $validUntil->format('Y-m-d H:i:s'),
            'client_ip'     => $clientIp,
            'user_agent'    => $userAgent,
        ]);
    }

    /**
     * Gibt alle Session-Records zurück (neueste zuerst).
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('user_sessions')
            ->orderBy('login_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Markiert eine Session anhand ihrer Datenbank-ID als ungültig.
     * Der Benutzer wird beim nächsten Request ausgeloggt.
     */
    public function invalidate(int $id): void
    {
        $this->connection->update(
            'user_sessions',
            ['is_valid' => 0],
            ['id' => $id],
        );
    }

    /**
     * Markiert die Session mit dem gegebenen Token als ungültig.
     * Wird beim Logout aufgerufen.
     */
    public function invalidateByToken(string $sessionToken): void
    {
        if ($sessionToken === '') {
            return;
        }
        $this->connection->update(
            'user_sessions',
            ['is_valid' => 0],
            ['session_token' => $sessionToken],
        );
    }

    /**
     * Prüft ob die Session mit dem gegebenen Token noch gültig ist.
     *
     * Rückgabe-Semantik:
     *   - Token nicht in DB (z.B. Sessions vor Einführung dieses Features) → true (Abwärtskompatibilität)
     *   - is_valid = false → false
     *   - valid_until in der Vergangenheit → false
     *   - Sonst → true
     */
    public function isValid(string $sessionToken): bool
    {
        if ($sessionToken === '') {
            return true;
        }

        $row = $this->connection->createQueryBuilder()
            ->select('is_valid', 'valid_until')
            ->from('user_sessions')
            ->where('session_token = :token')
            ->setParameter('token', $sessionToken)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        // Kein Eintrag = Session wurde vor Einführung des Trackings angelegt.
        // Abwärtskompatibel: erlauben.
        if ($row === false) {
            return true;
        }

        if (!(bool)$row['is_valid']) {
            return false;
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');
        return (string)($row['valid_until'] ?? '') >= $now;
    }

    /**
     * Löscht abgelaufene Sessions, die seit mehr als $days Tagen ungültig sind.
     * Kann als Cronjob oder beim Login aufgerufen werden.
     */
    public function purgeExpired(int $days = 30): void
    {
        $cutoff = $this->clock->now()->modify("-{$days} days")->format('Y-m-d H:i:s');

        $this->connection->createQueryBuilder()
            ->delete('user_sessions')
            ->where('valid_until < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->executeStatement();
    }
}
