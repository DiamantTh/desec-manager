<?php

declare(strict_types=1);

namespace App\InputFilter;

use Laminas\Filter\StringTrim;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;

/**
 * Validiert Passwort-Änderungs-Formulardaten (POST /profile, action=change_password).
 *
 * Felder: current_password, new_password, new_password_confirm
 *
 * Hinweis: Die Übereinstimmung von new_password und new_password_confirm
 * wird im Handler geprüft (Identical-Validator erfordert Zugriff auf das andere Feld).
 */
/** @extends InputFilter<array<string, mixed>> */
final class ChangePasswordInputFilter extends InputFilter
{
    public function __construct()
    {
        $current = new Input('current_password');
        $current->setRequired(true);
        $current->getFilterChain()->attach(new StringTrim());
        $current->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new StringLength(['min' => 1, 'max' => 1024]));

        $new = new Input('new_password');
        $new->setRequired(true);
        $new->getFilterChain()->attach(new StringTrim());
        $new->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new StringLength(['min' => 1, 'max' => 1024]));

        $confirm = new Input('new_password_confirm');
        $confirm->setRequired(true);
        $confirm->getFilterChain()->attach(new StringTrim());
        $confirm->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new StringLength(['min' => 1, 'max' => 1024]));

        $this->add($current);
        $this->add($new);
        $this->add($confirm);
    }
}
