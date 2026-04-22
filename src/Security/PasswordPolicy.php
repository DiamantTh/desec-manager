<?php

declare(strict_types=1);

namespace App\Security;

use ZxcvbnPhp\Zxcvbn;

/**
 * Kapselt die konfigurierbaren Passwort-Anforderungen.
 *
 * Konfiguration via [security.password] in config.toml:
 *   min_length = 16   # 8–128, Standard: 16
 *   min_score  = 0    # zxcvbn-Mindestscore: 0 = deaktiviert, 1–4
 */
final class PasswordPolicy
{
    /** Absolutes Minimum (gilt auch wenn die Konfiguration einen zu kleinen Wert enthält) */
    private const FLOOR = 8;

    /** Absolutes Maximum für min_length (verhindert unbrauchbar hohe Konfigurationswerte) */
    private const CEILING = 128;

    private int $minLength;
    private int $minScore;

    public function __construct(int $minLength = 16, int $minScore = 0)
    {
        $this->minLength = max(self::FLOOR, min(self::CEILING, $minLength));
        $this->minScore  = max(0, min(4, $minScore));
    }

    public function getMinLength(): int
    {
        return $this->minLength;
    }

    public function getMinScore(): int
    {
        return $this->minScore;
    }

    /**
     * Prüft, ob das Passwort die konfigurierten Anforderungen erfüllt.
     *
     * @throws \InvalidArgumentException wenn Länge oder zxcvbn-Score nicht ausreicht
     */
    public function assertValid(string $password): void
    {
        if (mb_strlen($password) < $this->minLength) {
            throw new \InvalidArgumentException(
                sprintf(
                    /* translators: %d = konfigurierte Mindestlänge */
                    __('The password must be at least %d characters long.'),
                    $this->minLength
                )
            );
        }

        if ($this->minScore > 0) {
            $result = (new Zxcvbn())->passwordStrength($password);
            if ($result['score'] < $this->minScore) {
                $suggestions = $result['feedback']['suggestions'] ?? [];
                $hint = $suggestions ? ' ' . implode(' ', $suggestions) : '';
                throw new \InvalidArgumentException(
                    __('The password is too weak.') . $hint
                );
            }
        }
    }

    /**
     * Berechnet den zxcvbn-Score des Passworts (0–4).
     *
     * Gibt score = -1 zurück, wenn die Bibliothek nicht verfügbar ist.
     *
     * @return array{score: int, feedback: array<string, mixed>}
     */
    public function score(string $password): array
    {
        if (!class_exists(Zxcvbn::class)) {
            return ['score' => -1, 'feedback' => ['suggestions' => [], 'warning' => '']];
        }

        /** @var array{score: int, feedback: array<string, mixed>} $result */
        $result = (new Zxcvbn())->passwordStrength($password);

        return $result;
    }
}
