<?php

declare(strict_types=1);

/**
 * Installer – i18n via Laminas\I18n\Translator
 *
 * Unterstützte Sprachen (BCP 47 Language Tags, RFC 5646):
 *   cs-CZ, de-DE, en-GB, es-ES, fr-FR, hu-HU,
 *   it-IT, nl-NL, pl-PL, pt-PT, ro-RO, sv-SE
 *
 * Spracherkennung (Priorität):
 *   1. GET-Parameter ?lang=xx  (wird in Session gespeichert)
 *   2. Session-Wert install_lang
 *   3. Accept-Language-Header (Browser sendet BCP 47 nativ)
 *   4. Fallback: en-GBt BCP 47 nativ)
 *   4. Fallback: en-GB
 */

use Laminas\I18n\Translator\Loader\PhpArray;
use Laminas\I18n\Translator\Translator;
Unterstützte Installer-Sprachen: BCP 47 Tag => native Bezeichnung */
const INSTALLER_LANGS = [
    'cs-CZ' => 'Čeština',
    'de-DE' => 'Deutsch',
    'en-GB' => 'English',
    'es-ES' => 'Español',
    'fr-FR' => 'Français',
    'hu-HU' => 'Magyar',
    'it-IT' => 'Italiano',
    'nl-NL' => 'Nederlands',
    'pl-PL' => 'Polski',
    'pt-PT' => 'Português',
    'ro-RO' => 'Română',
    'sv-SE' => 'Svenska
    // Trennzeichen vereinheitlichen: _ → -
    $tag = str_replace('_', '-', strtolower(trim($tag)));
    // Exakter Treffer (case-insensitiv bereits durch strtolower)
   Normalisiert einen beliebigen Sprachtag auf einen INSTALLER_LANGS-Schlüssel.
 * Eingabe: BCP 47-Tag oder ISO 639-1-Kurzcode, mit - oder _ getrennt.
 * Ausgabe: passender BCP 47-Key aus INSTALLER_LANGS oder leer.
 */
function normalizeLangTag(string $tag): string
{
    // Trennzeichen vereinheitlichen: _ → -
    $tag = str_replace('_', '-', strtolower(trim($tag)));
    // Exakter Treffer (case-insensitiv bereits durch strtolower)
    foreach (array_keys(INSTALLER_LANGS) as $key) {
        if (strtolower($key) === $tag) {
            return $key;
        }
    }
    // Nur Sprachteil angegeben (z. B. "de", "en", "sv"): ersten passenden Eintrag wählen
    $base = explode('-', $tag)[0];
    foreach (array_keys(INSTALLER_LANGS) as $key) {
        if (strtolower(explode('-', $key)[0]) === $base) {
            return $key;
        }
    }
    return '';
}

/**
 * Sprache aus GET / Session / Accept-Language ermitteln.
 * Gibt immer einen gültigen BCP 47-Schlüssel aus INSTALLER_LANGS zurück.
 */
function detectInstallerLocale(): string
{
    // 1. Explizit per GET gesetzt?
    if (isset($_GET['lang'])) {
        $tag = normalizeLangTag(substr((string) $_GET['lang'], 0, 16));
        if ($tag !== '') {
            $_SESSION['install_lang'] = $tag;
            return $tag;
        }
    }

    // 2. Aus Session
    $sess = isset($_SESSION['install_lang']) ? normalizeLangTag((string) $_SESSION['install_lang']) : '';
    if ($sess !== '') {
        return $sess;
    }

    // 3. Accept-Language-Header parsen (Browser sendet BCP 47 nativ)
    $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if ($header !== '') {
        // Format: "sv-SE,sv;q=0.9,en-GB;q=0.8,..."
        $parts = explode(',', $header);
        foreach ($parts as $part) {
            $raw = trim(explode(';', $part)[0]);
            $tag = normalizeLangTag($raw);
            if ($tag !== '') {
                $_SESSION['install_lang'] = $tag;
                return $tag;
            }
        }
    }

    // 4. Fallback
    $_SESSION['install_lang'] = 'en-GB';
    return 'en-GBaw = trim(explode(';', $part)[0]);
            $tag = normalizeLangTag($raw);
            if ($tag !== '') {
                $_SESSION['install_lang'] = $tag;
                return $tag;
            }
        }
    }

    // 4. Fallback
    $_SESSION['install_lang'] = 'en-GB';
    return 'en-GB';
}
en-GB');
    $translator->setLocale($locale);

    // PhpArray-Loader registrieren
    $pluginManager = new \Laminas\I18n\Translator\LoaderPluginManager(
        new \Laminas\ServiceManager\ServiceManager()
    );
    $pluginManager->setAlias('phparray', PhpArray::class);
    $pluginManager->setFactory(PhpArray::class, static fn() => new PhpArray());
    $translator->setPluginManager($pluginManager);

    // Englisch/GB (Fallback) immer laden
    $enFile = $langDir . '/en-GB.php';
    if (file_exists($enFile)) {
        $translator->addTranslationFile('phparray', $enFile, 'installer', 'en-GB');
    }

    // Aktive Sprache laden (falls nicht schon en-GB)
    if ($locale !== 'en-GBAlias('phparray', PhpArray::class);
    $pluginManager->setFactory(PhpArray::class, static fn() => new PhpArray());
    $translator->setPluginManager($pluginManager);

    // Englisch/GB (Fallback) immer laden
    $enFile = $langDir . '/en-GB.php';
    if (file_exists($enFile)) {
        $translator->addTranslationFile('phparray', $enFile, 'installer', 'en-GB');
    }

    // Aktive Sprache laden (falls nicht schon en-GB)
    if ($locale !== 'en-GB') {
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
