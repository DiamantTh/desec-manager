<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Kryptographisch sicherer Passwort-Generator.
 *
 * Zwei Modi:
 *   random()     → zufällige Zeichenfolge aus erweitertem ASCII-Alphabet
 *   passphrase() → XKCD-style Passphrase (Wort-Wort-Wort-Wort)
 *
 * Verwendet ausschließlich random_int() (CSPRNG) — kein mt_rand().
 *
 * Wortlisten werden aus Dateien in data/wordlists/{lang}.txt geladen.
 * Quellen:
 *   en  – EFF Large Wordlist (7776 Wörter) — https://www.eff.org/deeplinks/2016/07/new-wordlists-random-passphrases
 *   de  – dys2p wordlists-de (7776 Wörter, kein Umlaut) — https://github.com/dys2p/wordlists-de
 *   fr  – BIP-39 French, ASCII-gefiltert (1682 Wörter) — https://github.com/bitcoin/bips/blob/master/bip-0039/french.txt
 *   es  – BIP-39 Spanish, ASCII-gefiltert (1714 Wörter) — https://github.com/bitcoin/bips/blob/master/bip-0039/spanish.txt
 *   it  – BIP-39 Italian (2048 Wörter) — https://github.com/bitcoin/bips/blob/master/bip-0039/italian.txt
 *   pt  – BIP-39 Portuguese (2048 Wörter) — https://github.com/bitcoin/bips/blob/master/bip-0039/portuguese.txt
 *   cs  – BIP-39 Czech (2048 Wörter) — https://github.com/bitcoin/bips/blob/master/bip-0039/czech.txt
 *
 * Entropie (EN/DE 7776 Wörter = 12,9 bit/Wort):
 *   4 Wörter: ~51 bit
 *   5 Wörter: ~64 bit  (empfohlen)
 *   6 Wörter: ~77 bit
 *
 * Entropie (FR/ES/IT/PT/CS ~2048 Wörter = 11 bit/Wort):
 *   5 Wörter: ~55 bit  (empfohlen)
 *   6 Wörter: ~66 bit
 */
final class PasswordGenerator
{
    // -------------------------------------------------------------------------
    // Zeichensätze für Zufalls-Passwörter
    // -------------------------------------------------------------------------
    private const ALPHA   = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const DIGITS  = '0123456789';
    private const SYMBOLS = '!@#$%&*-+=?';

    private const WORDLIST_DIR = __DIR__ . '/../../data/wordlists';

    private const SUPPORTED_LANGS = ['en', 'de', 'fr', 'es', 'it', 'pt', 'cs'];

    /** @var string[] */
    private array $wordlistCache = [];

    public function __construct(private readonly string $lang = 'en') {}

    // =========================================================================
    // Öffentliche API
    // =========================================================================

    /**
     * Erzeugt ein zufälliges Passwort.
     *
     * @param int  $length  Zeichenanzahl (empfohlen: ≥ 16)
     * @param bool $symbols Sonderzeichen einschließen (Standard: true)
     */
    public function random(int $length = 20, bool $symbols = true): string
    {
        $charset = self::ALPHA . self::DIGITS . ($symbols ? self::SYMBOLS : '');
        $max     = strlen($charset) - 1;
        $result  = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $charset[random_int(0, $max)];
        }

        return $result;
    }

    /**
     * Erzeugt eine XKCD-style Passphrase aus mehreren zufälligen Wörtern.
     *
     * Die Wortliste wird aus data/wordlists/{lang}.txt geladen (Sprache via Konstruktor).
     * Fallback auf 'en' wenn die gewünschte Sprachdatei nicht existiert.
     *
     * @param int    $wordCount  Anzahl der Wörter (Standard: 5)
     * @param string $separator  Trennzeichen (Standard: -)
     */
    public function passphrase(int $wordCount = 5, string $separator = '-'): string
    {
        $list  = $this->getWordlist();
        $max   = count($list) - 1;

        if ($max < 0) {
            throw new \RuntimeException('Wordlist could not be loaded.');
        }

        $words = [];

        for ($i = 0; $i < $wordCount; $i++) {
            $words[] = $list[random_int(0, $max)];
        }

        return implode($separator, $words);
    }

    /**
     * Gibt mehrere Vorschläge pro Typ zurück.
     *
     * @return array<string, list<string>>  Keys: 'random', 'passphrase'
     */
    public function suggestions(int $count = 5): array
    {
        $result = ['random' => [], 'passphrase' => []];

        for ($i = 0; $i < $count; $i++) {
            $result['random'][]     = $this->random(20, true);
            $result['passphrase'][] = $this->passphrase(5, '-');
        }

        return $result;
    }

    /**
     * Aktuell geladene Sprache (nach Fallback-Auflösung).
     */
    public function getActiveLang(): string
    {
        $path = self::WORDLIST_DIR . '/' . $this->lang . '.txt';
        return file_exists($path) ? $this->lang : 'en';
    }

    /**
     * Anzahl der Wörter in der aktiven Wortliste (für Entropie-Berechnung).
     */
    public function wordlistSize(): int
    {
        return count($this->getWordlist());
    }

    // =========================================================================
    // Hilfsmethoden (privat)
    // =========================================================================

    /**
     * Lädt die Wortliste für die konfigurierte Sprache (lazy, gecacht).
     *
     * Unterstützte Sprachen: en, de, fr, es, it, pt, cs
     * Fallback: en
     *
     * @return string[]
     */
    private function getWordlist(): array
    {
        if ($this->wordlistCache !== []) {
            return $this->wordlistCache;
        }

        $lang = in_array($this->lang, self::SUPPORTED_LANGS, true) ? $this->lang : 'en';
        $path = self::WORDLIST_DIR . '/' . $lang . '.txt';

        if (!file_exists($path)) {
            $path = self::WORDLIST_DIR . '/en.txt';
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->wordlistCache = $lines !== false ? $lines : [];

        return $this->wordlistCache;
    }
}
