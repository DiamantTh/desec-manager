<?php

declare(strict_types=1);

namespace App\Security;

class DomainValidator
{
    private const VALID_DEVELOPMENT_DOMAINS = [
        'localhost',
        '.localhost'  // Allows subdomains of localhost
    ];

    /**
     * Checks whether a domain is suitable for WebAuthn.
     *
     * @return array{valid: bool, reason: string}
     */
    public static function validateForWebAuthn(string $domain): array
    {
        // Development domains
        foreach (self::VALID_DEVELOPMENT_DOMAINS as $devDomain) {
            if ($devDomain[0] === '.' && str_ends_with($domain, $devDomain)) {
                return [
                    'valid'  => true,
                    'reason' => 'Development domain (local testing only)',
                ];
            }
            if ($domain === $devDomain) {
                return [
                    'valid'  => true,
                    'reason' => 'Local development',
                ];
            }
        }

        // Check for IP address
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return [
                'valid'  => false,
                'reason' => 'IP addresses are not allowed for WebAuthn',
            ];
        }

        // Basic domain validation
        if (!preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*\.[a-z]{2,}$/i', $domain)) {
            return [
                'valid'  => false,
                'reason' => 'Invalid domain format',
            ];
        }

        // Extract TLD
        $parts = explode('.', $domain);
        $tld   = strtolower(end($parts));

        // Known invalid TLDs
        $invalidTlds = [
            'local',
            'internal',
            'test',
            'invalid',
            'example',
            'dev',  // The old .dev TLD, not Google's new .dev
        ];

        if (in_array($tld, $invalidTlds)) {
            return [
                'valid'  => false,
                'reason' => "TLD '.$tld' is not allowed for WebAuthn",
            ];
        }

        // List of valid TLDs (should be updated regularly)
        $validTlds = [
            // Generic TLDs
            'com', 'org', 'net', 'edu', 'gov', 'mil', 'int',
            // Country Code TLDs
            'de', 'uk', 'fr', 'it', 'es', 'nl', 'be', 'at', 'ch',
            // New gTLDs
            'io', 'dev', 'app', 'cloud', 'online', 'tech', 'site',
            // Add more TLDs as needed
        ];

        if (!in_array($tld, $validTlds)) {
            return [
                'valid'  => false,
                'reason' => "Unknown or unsupported TLD '.$tld'",
            ];
        }

        return [
            'valid'  => true,
            'reason' => 'Domain is suitable for WebAuthn',
        ];
    }

    /**
     * Checks whether HTTPS should be enforced for this domain.
     */
    public static function requiresHttps(string $domain): bool
    {
        // Localhost may use HTTP
        if ($domain === 'localhost' || str_ends_with($domain, '.localhost')) {
            return false;
        }

        return true;
    }

    /**
     * Returns a user-friendly WebAuthn recommendation for the given domain.
     */
    public static function getWebAuthnRecommendation(string $domain): string
    {
        $result = self::validateForWebAuthn($domain);

        if (!$result['valid']) {
            if (filter_var($domain, FILTER_VALIDATE_IP)) {
                return "WebAuthn requires a real domain name. "
                     . "Please configure a domain name instead of the IP address {$domain}.";
            }

            return "The domain {$domain} cannot be used for WebAuthn. "
                 . "Reason: {$result['reason']}\n"
                 . "Recommendation: Use a domain with an official TLD such as .com, .org, .de etc.";
        }

        if ($domain === 'localhost' || str_ends_with($domain, '.localhost')) {
            return "The domain {$domain} is suitable for development purposes only. "
                 . "For production environments please use a real domain.";
        }

        return "The domain {$domain} is suitable for WebAuthn.";
    }
}
