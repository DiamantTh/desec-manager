<?php

declare(strict_types=1);

namespace App\InputFilter;

use Laminas\Filter\Digits;
use Laminas\Filter\StringTrim;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Digits as DigitsValidator;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;

/**
 * Validiert TOTP-Bestätigungscodes (POST /auth/mfa/totp).
 *
 * Felder: code (6 Ziffern)
 */
final class TotpInputFilter extends InputFilter
{
    public function __construct()
    {
        $code = new Input('code');
        $code->setRequired(true);
        $code->getFilterChain()
            ->attach(new StringTrim())
            ->attach(new Digits());
        $code->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new DigitsValidator())
            ->attach(new StringLength(['min' => 6, 'max' => 8]));

        $this->add($code);
    }
}
