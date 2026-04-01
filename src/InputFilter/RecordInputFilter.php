<?php

declare(strict_types=1);

namespace App\InputFilter;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\Filter\ToInt;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\Validator\Between;
use Laminas\Validator\InArray;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;

/**
 * Validiert DNS-Record-Formulardaten (POST /domains/{domain}/records/add, /edit).
 *
 * Felder: type, subname, ttl, records
 */
final class RecordInputFilter extends InputFilter
{
    /** Erlaubte DNS-Record-Typen (deSEC-Subset). */
    private const ALLOWED_TYPES = [
        'A', 'AAAA', 'CAA', 'CERT', 'CNAME', 'DS', 'HTTPS', 'LOC',
        'MX', 'NAPTR', 'NS', 'PTR', 'SSHFP', 'SRV', 'SVCB', 'TLSA', 'TXT',
    ];

    public function __construct()
    {
        $type = new Input('type');
        $type->setRequired(true);
        $type->getFilterChain()
            ->attach(new StripTags())
            ->attach(new StringTrim())
            ->attach(new \Laminas\Filter\StringToUpper());
        $type->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new InArray(['haystack' => self::ALLOWED_TYPES, 'strict' => InArray::COMPARE_STRICT]));

        $subname = new Input('subname');
        $subname->setRequired(false);
        $subname->setAllowEmpty(true);
        $subname->getFilterChain()
            ->attach(new StripTags())
            ->attach(new StringTrim());
        $subname->getValidatorChain()
            ->attach(new StringLength(['min' => 0, 'max' => 253]));

        $ttl = new Input('ttl');
        $ttl->setRequired(true);
        $ttl->getFilterChain()
            ->attach(new ToInt());
        $ttl->getValidatorChain()
            ->attach(new Between(['min' => 1, 'max' => 86400, 'inclusive' => true]));

        $records = new Input('records');
        $records->setRequired(true);
        $records->getFilterChain()
            ->attach(new StringTrim());
        $records->getValidatorChain()
            ->attach(new NotEmpty())
            ->attach(new StringLength(['min' => 1, 'max' => 65535]));

        $this->add($type);
        $this->add($subname);
        $this->add($ttl);
        $this->add($records);
    }
}
