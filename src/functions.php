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
