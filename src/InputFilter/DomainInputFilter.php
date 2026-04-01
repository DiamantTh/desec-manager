<?php

declare(strict_types=1);

namespace App\InputFilter;

use Laminas\Filter\StringToLower;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Hostname;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;

/**
 * Validiert Domain-Formulardaten (POST /domains/add).
 *
 * Felder: domain (vollqualifizierter Hostname), api_key_id (int)
 */
final class DomainInputFilter extends InputFilter
{
    public function __construct()
    {
        $domain = new Input('domain');
        $domain->setRequired(true);
        $domain->getFilterChain()
            ->attach(new StripTags())
            ->attach(new StringTrim())
            ->attach(new StringToLower());
        $domain->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new StringLength(['min' => 3, 'max' => 253]))
            ->attach(new Hostname([
                'allow'           => Hostname::ALLOW_DNS,
                'useIdnCheck'     => true,
                'useTldCheck'     => false,  // Subdomains ohne TLD-Pflicht erlauben
            ]));

        $apiKeyId = new Input('api_key_id');
        $apiKeyId->setRequired(true);
        $apiKeyId->getFilterChain()
            ->attach(new \Laminas\Filter\ToInt());
        $apiKeyId->getValidatorChain()
            ->attach(new \Laminas\Validator\GreaterThan(['min' => 0, 'inclusive' => false]));

        $this->add($domain);
        $this->add($apiKeyId);
    }
}
