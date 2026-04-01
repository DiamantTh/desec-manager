<?php

declare(strict_types=1);

namespace App\InputFilter;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;

/**
 * Validiert Login-Formulardaten (POST /auth/login).
 *
 * Felder: username, password
 */
final class LoginInputFilter extends InputFilter
{
    public function __construct()
    {
        $username = new Input('username');
        $username->setRequired(true);
        $username->getFilterChain()
            ->attach(new StripTags())
            ->attach(new StringTrim());
        $username->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new StringLength(['min' => 1, 'max' => 150]));

        $password = new Input('password');
        $password->setRequired(true);
        $password->getFilterChain()
            ->attach(new StringTrim());
        $password->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new StringLength(['min' => 1, 'max' => 1024]));

        $this->add($username);
        $this->add($password);
    }
}
