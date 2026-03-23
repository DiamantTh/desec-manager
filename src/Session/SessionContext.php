<?php

declare(strict_types=1);

namespace App\Session;

use Mezzio\Session\SessionInterface;

/**
 * SessionContext — request-scoped Session-Wrapper für DI.
 *
 * PHP-DI erstellt diese Klasse als Singleton. Jede Request-Verarbeitung
 * ruft initialize() über den SessionContextMiddleware auf, bevor Handler laufen.
 *
 * Warum kein direktes $request->getAttribute(SessionInterface::class)?
 *   Security-Services (UserKeyManager, WebAuthnService) und AbstractHandler-Helpers
 *   können den Request nicht injizieren. SessionContext löst das, indem die
 *   mezzio/mezzio-session SessionInterface nach dem Session-Start hier
 *   hinterlegt wird.
 *
 * Invariante: initialize() wird exakt einmal pro Request aufgerufen, bevor
 * irgendeine Methode dieser Klasse aufgerufen wird.
 */
final class SessionContext
{
    private ?SessionInterface $session = null;

    /** Wird von SessionContextMiddleware nach dem mezzio-Session-Start aufgerufen. */
    public function initialize(SessionInterface $session): void
    {
        $this->session = $session;
    }

    public function isInitialized(): bool
    {
        return $this->session !== null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->session?->get($key, $default) ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->session?->set($key, $value);
    }

    public function unset(string $key): void
    {
        $this->session?->unset($key);
    }

    public function has(string $key): bool
    {
        return $this->session?->has($key) ?? false;
    }

    /** Markiert die Session zur ID-Regenerierung (Schutz vor Session-Fixation). */
    public function regenerate(): void
    {
        if ($this->session !== null) {
            $this->session = $this->session->regenerate();
        }
    }

    /** Leert alle Session-Daten (Logout). */
    public function clear(): void
    {
        $this->session?->clear();
    }

    // -------------------------------------------------------------------------
    // Locale-Helpers (Shortcut für Translator-Nutzung)
    // -------------------------------------------------------------------------

    public function getLocale(): string
    {
        return (string) ($this->session?->get('locale') ?? 'en_US');
    }

    public function setLocale(string $locale): void
    {
        $this->session?->set('locale', $locale);
    }
}
