<?php
declare(strict_types=1);
namespace App\Service;

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
    private static array $translations = [];
    private static string $localeDir = '';

    public static function init(string $localeDir): void
    {
        self::$localeDir = $localeDir;
        $locale = self::detectLocale();
        self::$currentLocale = $locale;

        // Compile .mo if needed
        $poFile = $localeDir . '/' . $locale . '/LC_MESSAGES/' . self::DOMAIN . '.po';
        $moFile = $localeDir . '/' . $locale . '/LC_MESSAGES/' . self::DOMAIN . '.mo';
        if (file_exists($poFile) && (!file_exists($moFile) || filemtime($poFile) > filemtime($moFile))) {
            PoToMoCompiler::compile($poFile, $moFile);
        }

        // Load translations into memory
        if (file_exists($moFile)) {
            self::$translations = self::loadMoFile($moFile);
        }

        // Also try to set system locale (may not work but doesn't hurt)
        putenv('LANGUAGE=' . $locale);
        putenv('LC_ALL=' . $locale . '.UTF-8');
        setlocale(LC_MESSAGES, $locale . '.UTF-8', $locale, 'C.UTF-8', 'C');
        bindtextdomain(self::DOMAIN, $localeDir);
        bind_textdomain_codeset(self::DOMAIN, 'UTF-8');
        textdomain(self::DOMAIN);
    }

    public static function getCurrentLocale(): string
    {
        return self::$currentLocale;
    }

    public static function translate(string $msgid): string
    {
        // Try our in-memory translations first
        if (isset(self::$translations[$msgid]) && self::$translations[$msgid] !== '') {
            return self::$translations[$msgid];
        }
        
        // Fall back to gettext (in case system locale works)
        $result = gettext($msgid);
        if ($result !== '' && $result !== $msgid) {
            return $result;
        }
        
        // Return original msgid as fallback
        return $msgid;
    }

    private static function detectLocale(): string
    {
        // 1. POST request to change locale
        if (isset($_POST['_locale']) && isset(self::SUPPORTED_LOCALES[$_POST['_locale']])) {
            $_SESSION['locale'] = $_POST['_locale'];
        }

        // 2. Session
        if (!empty($_SESSION['locale']) && isset(self::SUPPORTED_LOCALES[$_SESSION['locale']])) {
            return $_SESSION['locale'];
        }

        // 3. Accept-Language header
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
     * Load .mo file and parse into translations array
     * @return array<string, string>
     */
    private static function loadMoFile(string $moFile): array
    {
        $data = file_get_contents($moFile);
        if ($data === false || strlen($data) < 28) {
            return [];
        }

        // Read header
        $header = unpack('Vmagic/Vrevision/VN/VorigOffset/VtransOffset', substr($data, 0, 20));
        
        if ($header['magic'] !== 0x950412de) {
            return [];  // Invalid magic number
        }

        $n = $header['N'];
        $origOffset = $header['origOffset'];
        $transOffset = $header['transOffset'];

        $translations = [];

        // Read original and translation string tables
        for ($i = 0; $i < $n; $i++) {
            // Original string
            $origEntry = unpack('Vlength/Voffset', substr($data, $origOffset + $i * 8, 8));
            $origString = substr($data, $origEntry['offset'], $origEntry['length']);
            
            // Translation string
            $transEntry = unpack('Vlength/Voffset', substr($data, $transOffset + $i * 8, 8));
            $transString = substr($data, $transEntry['offset'], $transEntry['length']);
            
            if ($origString !== '') {
                $translations[$origString] = $transString;
            }
        }

        return $translations;
    }
}
