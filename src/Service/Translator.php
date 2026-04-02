<?php
declare(strict_types=1);
namespace App\Service;

/**
 * Translator — globale Übersetzungs-Fassade für die `__()` Funktion.
 *
 * Primär-Backend: laminas/laminas-i18n (Translator-Instanz, konfiguriert in
 * config/container.php und initialisiert von SessionContextMiddleware).
 *
 * Legacy-Fallback: hand-gefertigter .mo-Binary-Parser (bleibt für CLI-Tools und
 * Kontexte, in denen SessionContextMiddleware nicht läuft, z. B. install.php).
 *
 * Verwendung:
 *   SessionContextMiddleware ruft Translator::setLaminasTranslator($t) auf,
 *   danach leitet translate() alle Aufrufe an die Laminas-Instanz weiter.
 */
class Translator
{
    public const DOMAIN = 'desec-manager';

    /** @var array<string, string> locale => display name */
    public const SUPPORTED_LOCALES = [
        'en_US' => 'English',
        'de_DE' => 'Deutsch',
        'fr_FR' => 'Français',
        'es_ES' => 'Español',
        'it_IT' => 'Italiano',
        'pl_PL' => 'Polski',
        'nl_NL' => 'Nederlands',
        'pt_PT' => 'Português',
        'cs_CZ' => 'Čeština',
        'ro_RO' => 'Română',
        'hu_HU' => 'Magyar',
        'sv_SE' => 'Svenska',
    ];

    private static string $currentLocale = 'en_US';

    /** @var array<string, string> Legacy in-memory translations (CLI fallback) */
    private static array $translations = [];

    private static ?\Laminas\I18n\Translator\Translator $laminas = null;

    // -------------------------------------------------------------------------
    // Laminas-Backend (PSR-15 Web-Kontext)
    // -------------------------------------------------------------------------

    /**
     * Setzt die laminas-i18n Translator-Instanz.
     * Wird von SessionContextMiddleware pro Request aufgerufen.
     */
    public static function setLaminasTranslator(\Laminas\I18n\Translator\Translator $translator): void
    {
        self::$laminas       = $translator;
        self::$currentLocale = $translator->getLocale();
    }

    // -------------------------------------------------------------------------
    // Legacy-/CLI-Initialisierung (.mo-Datei direkt einlesen)
    // -------------------------------------------------------------------------

    /**
     * Initialisiert die Übersetzungen direkt aus einem locale-Verzeichnis.
     * Wird von CLI-Tools wie install.php verwendet, die kein PSR-15-Pipeline haben.
     * Im normalen Web-Request ist dies ein No-op (SessionContextMiddleware übernimmt).
     */
    public static function init(string $localeDir): void
    {
        // Im Web-Kontext: SessionContextMiddleware hat bereits setLaminasTranslator() aufgerufen.
        if (self::$laminas !== null) {
            return;
        }

        $locale = self::detectLocaleLegacy($localeDir);
        self::$currentLocale = $locale;

        // Compile .mo if needed
        $poFile = $localeDir . '/' . $locale . '/LC_MESSAGES/' . self::DOMAIN . '.po';
        $moFile = $localeDir . '/' . $locale . '/LC_MESSAGES/' . self::DOMAIN . '.mo';
        if (file_exists($poFile) && (!file_exists($moFile) || filemtime($poFile) > filemtime($moFile))) {
            PoToMoCompiler::compile($poFile, $moFile);
        }

        if (file_exists($moFile)) {
            self::$translations = self::loadMoFile($moFile);
        }
    }

    public static function getCurrentLocale(): string
    {
        return self::$currentLocale;
    }

    // -------------------------------------------------------------------------
    // Übersetzung
    // -------------------------------------------------------------------------

    public static function translate(string $msgid): string
    {
        // 1. Laminas-Backend (Web-Kontext, Normalfall)
        if (self::$laminas !== null) {
            $result = self::$laminas->translate($msgid, self::DOMAIN);
            return ($result !== '' && $result !== $msgid) ? $result : $msgid;
        }

        // 2. Legacy in-memory (CLI-Kontext)
        if (isset(self::$translations[$msgid]) && self::$translations[$msgid] !== '') {
            return self::$translations[$msgid];
        }

        return $msgid;
    }

    // -------------------------------------------------------------------------
    // Private Hilfsmethoden
    // -------------------------------------------------------------------------

    /**
     * Locale-Erkennung für Legacy/CLI-Kontext (kein PSR-15 Request-Objekt verfügbar).
     */
    private static function detectLocaleLegacy(string $localeDir): string
    {
        // POST param
        if (isset($_POST['_locale']) && isset(self::SUPPORTED_LOCALES[$_POST['_locale']])) {
            return (string) $_POST['_locale'];
        }

        // Accept-Language header
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($header !== '') {
            foreach (self::parseAcceptLanguage($header) as $lang) {
                foreach (array_keys(self::SUPPORTED_LOCALES) as $locale) {
                    if (str_starts_with(strtolower($locale), strtolower(str_replace('-', '_', $lang)))) {
                        return $locale;
                    }
                }
            }
        }

        return 'en_US';
    }

    /** @return string[] */
    private static function parseAcceptLanguage(string $header): array
    {
        $parts = explode(',', $header);
        $langs = [];
        foreach ($parts as $part) {
            $segments = explode(';', trim($part));
            $langs[] = trim($segments[0]);
        }
        return $langs;
    }

    /**
     * Liest eine .mo-Datei ein und liefert ein Translations-Array.
     * Wird nur im Legacy/CLI-Kontext verwendet (wenn kein Laminas-Backend vorhanden).
     *
     * @return array<string, string>
     */
    private static function loadMoFile(string $moFile): array
    {
        $data = file_get_contents($moFile);
        if ($data === false || strlen($data) < 28) {
            return [];
        }

        $header = unpack('Vmagic/Vrevision/VN/VorigOffset/VtransOffset', substr($data, 0, 20));
        if (!is_array($header) || $header['magic'] !== 0x950412de) {
            return [];
        }

        $n           = $header['N'];
        $origOffset  = $header['origOffset'];
        $transOffset = $header['transOffset'];

        $translations = [];
        for ($i = 0; $i < $n; $i++) {
            $origEntry   = unpack('Vlength/Voffset', substr($data, $origOffset  + $i * 8, 8));
            $transEntry  = unpack('Vlength/Voffset', substr($data, $transOffset + $i * 8, 8));
            if (!is_array($origEntry) || !is_array($transEntry)) {
                continue;
            }
            $origString  = substr($data, $origEntry['offset'],  $origEntry['length']);
            $transString = substr($data, $transEntry['offset'], $transEntry['length']);
            if ($origString !== '') {
                $translations[$origString] = $transString;
            }
        }

        return $translations;
    }
}
