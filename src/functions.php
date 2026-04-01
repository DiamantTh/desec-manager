<?php
declare(strict_types=1);

if (!function_exists('__')) {
    /**
     * Translate a string using gettext.
     */
    function __(string $msgid): string
    {
        return \App\Service\Translator::translate($msgid);
    }
}

if (!function_exists('_n')) {
    /**
     * Translate a plural string using ngettext.
     */
    function _n(string $singular, string $plural, int $n): string
    {
        $result = ngettext($singular, $plural, $n);
        // If ngettext doesn't work, fall back to simple logic
        if ($result === $singular || $result === $plural) {
            return $n === 1 ? \App\Service\Translator::translate($singular) : \App\Service\Translator::translate($plural);
        }
        return $result;
    }
}

/**
 * Converts a Unicode domain name to its ACE/Punycode form (RFC 3492).
 *
 * "müller.eu"  →  "xn--mller-kva.eu"
 * "example.com" →  "example.com"  (unchanged, already ASCII)
 *
 * Requires ext-intl. Throws if the extension is unavailable or the input
 * is not a valid IDNA label.
 *
 * @throws \RuntimeException if ext-intl is not loaded
 * @throws \InvalidArgumentException if $domain cannot be converted
 */
if (!function_exists('domain_to_ace')) {
    function domain_to_ace(string $domain): string
    {
        if (!function_exists('idn_to_ascii')) {
            throw new \RuntimeException(
                'ext-intl is required for international domain names (IDN). ' .
                'Please install the PHP intl extension.'
            );
        }

        // Already pure ASCII → skip conversion
        if (!preg_match('/[^\x00-\x7F]/', $domain)) {
            return $domain;
        }

        $ace = idn_to_ascii($domain, INTL_IDNA_VARIANT_UTS46);
        if ($ace === false) {
            throw new \InvalidArgumentException(__('Invalid domain name.'));
        }

        return $ace;
    }
}

/**
 * Converts an ACE/Punycode domain name to its Unicode form for display.
 *
 * "xn--mller-kva.eu"  →  "müller.eu"
 * "example.com"        →  "example.com"  (unchanged)
 * Falls back to ACE form silently if ext-intl is unavailable.
 */
if (!function_exists('domain_to_unicode')) {
    function domain_to_unicode(string $domain): string
    {
        if (!function_exists('idn_to_utf8')) {
            return $domain;
        }

        // Only convert if domain contains ACE labels ("xn--")
        if (!str_contains($domain, 'xn--')) {
            return $domain;
        }

        $unicode = idn_to_utf8($domain, INTL_IDNA_VARIANT_UTS46);
        return ($unicode !== false) ? $unicode : $domain;
    }
}

