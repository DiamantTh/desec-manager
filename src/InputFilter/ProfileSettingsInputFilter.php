<?php

declare(strict_types=1);

namespace App\InputFilter;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;

/**
 * Validiert Profil-Einstellungen (POST /profile, action=update_settings).
 *
 * Felder: theme (optionaler String), locale (optionaler Locale-Code)
 */
/** @extends InputFilter<array<string, mixed>> */
final class ProfileSettingsInputFilter extends InputFilter
{
    /** Unterstützte Themes (muss mit ThemeManager::getAvailableThemes() übereinstimmen). */
    private const ALLOWED_THEMES = ['default', 'bulma', 'svelte'];

    /** Unterstützte Locales (muss mit Translator::SUPPORTED_LOCALES übereinstimmen). */
    private const ALLOWED_LOCALES = [
        'en_US', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pl_PL',
        'nl_NL', 'pt_PT', 'cs_CZ', 'ro_RO', 'hu_HU', 'sv_SE',
    ];

    public function __construct()
    {
        $theme = new Input('theme');
        $theme->setRequired(false);
        $theme->setAllowEmpty(true);
        $theme->getFilterChain()
            ->attach(new StripTags())
            ->attach(new StringTrim());
        $theme->getValidatorChain()
            ->attach(new StringLength(['min' => 0, 'max' => 64]))
            ->attach(new InArray(['haystack' => self::ALLOWED_THEMES, 'strict' => InArray::COMPARE_STRICT]));

        $locale = new Input('locale');
        $locale->setRequired(false);
        $locale->setAllowEmpty(true);
        $locale->getFilterChain()
            ->attach(new StripTags())
            ->attach(new StringTrim());
        $locale->getValidatorChain()
            ->attach(new StringLength(['min' => 0, 'max' => 10]))
            ->attach(new InArray(['haystack' => self::ALLOWED_LOCALES, 'strict' => InArray::COMPARE_STRICT]));

        $this->add($theme);
        $this->add($locale);
    }
}
