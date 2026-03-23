<?php

declare(strict_types=1);

namespace App\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * PSR-20 SystemClock — gibt die aktuelle Systemzeit zurück.
 *
 * Ermöglicht testbare Zeitstempel in Services und Repositories,
 * indem ClockInterface injiziert statt date() / new DateTimeImmutable() direkt aufgerufen wird.
 */
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
