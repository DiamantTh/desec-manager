<?php

declare(strict_types=1);

/**
 * Installer – i18n via Laminas\I18n\Translator
 *
 * Unterstützte EU-Sprachen (ISO 3166-1 Alpha-2):
 *   cz, de, gb, es, fr, hu, it, nl, pl, pt, ro, se
 *
 * Spracherkennung (Priorität):
 *   1. GET-Parameter ?lang=xx  (wird in Session gespeichert)
 *   2. Session-Wert install_lang
 *   3. Accept-Language-Header
 *   4. Fallback: gb (English/United Kingdom)
 */

use Laminas\I18n\Translator\Loader\PhpArray;
use Laminas\I18n\Translator\Translator;

/** Alle vom Installer unterstützten Sprachen: ISO 3166-1 Alpha-2 => native Bezeichnung */
const INSTALLER_LANGS = [
    'cz' => 'Čeština',
    'de' => 'Deutsch',
    'gb' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'hu' => 'Magyar',
    'it' => 'Italiano',
    'nl' => 'Nederlands',
    'pl' => 'Polski',
    'pt' => 'Português',
    'ro' => 'Română',
    'se' => 'Svenska',
];

/**
 * Mapping ISO 639-1 Sprachcode → ISO 3166-1 Ländercode
 * (nur für Codes, die voneinander abweichen)
 */
const LANG_ISO639_TO_ISO3166 = [
    'cs' => 'cz',
    'en' => 'gb',
    'sv' => 'se',
];

/**
 * Sprache aus GET / Session / Accept-Language ermitteln.
 * Gibt immer einen gültigen Schlüssel aus INSTALLER_LANGS zurück.
 */
function detectInstallerLocale(): string
{
    // 1. Explizit per GET gesetzt?
    $req = isset($_GET['lang']) ? strtolower(substr((string) $_GET['lang'], 0, 5)) : '';
    // Normalisieren: "de-AT", "de_AT" → "de" / "sv-SE" → "sv"
    $req = preg_replace('/[-_].+$/', '', $req) ?? '';
    // ISO 639-1 → ISO 3166-1 konvertieren (z. B. "sv" → "se")
    $req = LANG_ISO639_TO_ISO3166[$req] ?? $req;
    if (isset(INSTALLER_LANGS[$req])) {
        $_SESSION['install_lang'] = $req;
        return $req;
    }

    // 2. Aus Session (Migration alter Codes berücksichtigen)
    $sess = isset($_SESSION['install_lang']) ? (string) $_SESSION['install_lang'] : '';
    $sess = LANG_ISO639_TO_ISO3166[$sess] ?? $sess;
    if (isset(INSTALLER_LANGS[$sess])) {
        return $sess;
    }

    // 3. Accept-Language-Header parsen
    $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if ($header !== '') {
        // Format: "de-AT,de;q=0.9,en;q=0.8,..."
        $parts = explode(',', $header);
        foreach ($parts as $part) {
            $lang = strtolower(trim(explode(';', $part)[0]));
            $base = preg_replace('/[-_].+$/', '', $lang) ?? '';
            // ISO 639-1 → ISO 3166-1 konvertieren
            $base = LANG_ISO639_TO_ISO3166[$base] ?? $base;
            if (isset(INSTALLER_LANGS[$base])) {
                $_SESSION['install_lang'] = $base;
                return $base;
            }
        }
    }

    // 4. Fallback
    $_SESSION['install_lang'] = 'gb';
    return 'gb';
}

/**
 * Laminas Translator initialisieren und global registrieren.
 * Gibt den aktiven Locale-String zurück.
 */
function initTranslator(): string
{
    $locale = detectInstallerLocale();
    $langDir = __DIR__ . '/../lang';

    $translator = new Translator();
    $translator->setFallbackLocale('gb');
    $translator->setLocale($locale);

    // PhpArray-Loader registrieren
    $pluginManager = new \Laminas\I18n\Translator\LoaderPluginManager(
        new \Laminas\ServiceManager\ServiceManager()
    );
    $pluginManager->setAlias('phparray', PhpArray::class);
    $pluginManager->setFactory(PhpArray::class, static fn() => new PhpArray());
    $translator->setPluginManager($pluginManager);

    // Englisch/GB (Fallback) immer laden
    $gbFile = $langDir . '/gb.php';
    if (file_exists($gbFile)) {
        $translator->addTranslationFile('phparray', $gbFile, 'installer', 'gb');
    }

    // Aktive Sprache laden (falls nicht schon gb)
    if ($locale !== 'gb') {
        $localeFile = $langDir . '/' . $locale . '.php';
        if (file_exists($localeFile)) {
            $translator->addTranslationFile('phparray', $localeFile, 'installer', $locale);
        }
    }

    // Global verfügbar machen
    $GLOBALS['_installer_translator'] = $translator;
    $GLOBALS['_installer_locale']     = $locale;

    return $locale;
}

/**
 * Übersetzen mit optionalem sprintf-Formatierung.
 *
 * Beispiel:
 *   t('req.php_detail', '8.4.1')  →  'Installed: 8.4.1'
 *
 * @param string ...$args  Erster Arg = Schlüssel, weitere = sprintf-Parameter
 */
function t(string $key, string ...$args): string
{
    /** @var Translator|null $tr */
    $tr = $GLOBALS['_installer_translator'] ?? null;
    if ($tr === null) {
        // Translator noch nicht initialisiert → Schlüssel zurückgeben
        return $key;
    }

    $locale = $GLOBALS['_installer_locale'] ?? null;
    $translated = $tr->translate($key, 'installer', $locale);

    if ($args !== []) {
        $translated = sprintf($translated, ...$args);
    }

    return $translated;
}

/**
 * Plural-Übersetzung.
 *
 * @param int $n Anzahl
 */
function tn(string $singular, string $plural, int $n, string ...$args): string
{
    /** @var Translator|null $tr */
    $tr = $GLOBALS['_installer_translator'] ?? null;
    if ($tr === null) {
        return $n === 1 ? $singular : $plural;
    }

    $locale = $GLOBALS['_installer_locale'] ?? null;
    $translated = $tr->translatePlural($singular, $plural, $n, 'installer', $locale);

    if ($args !== []) {
        $translated = sprintf($translated, ...$args);
    }

    return $translated;
}
