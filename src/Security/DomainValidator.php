<?php
namespace App\Security;

class DomainValidator 
{
    private const VALID_DEVELOPMENT_DOMAINS = [
        'localhost',
        '.localhost'  // Erlaubt Subdomains von localhost
    ];

    /**
     * Prüft ob eine Domain für WebAuthn geeignet ist
     * 
     * @return array{valid: bool, reason: string}
     */
    public static function validateForWebAuthn(string $domain): array 
    {
        // Entwicklungsdomains
        foreach (self::VALID_DEVELOPMENT_DOMAINS as $devDomain) {
            if ($devDomain[0] === '.' && str_ends_with($domain, $devDomain)) {
                return [
                    'valid' => true,
                    'reason' => 'Entwicklungsdomäne (nur für lokale Tests)'
                ];
            }
            if ($domain === $devDomain) {
                return [
                    'valid' => true,
                    'reason' => 'Lokale Entwicklung'
                ];
            }
        }

        // Prüfe auf IP-Adresse
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return [
                'valid' => false,
                'reason' => 'IP-Adressen sind für WebAuthn nicht erlaubt'
            ];
        }

        // Grundlegende Domain-Validierung
        if (!preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*\.[a-z]{2,}$/i', $domain)) {
            return [
                'valid' => false,
                'reason' => 'Ungültiges Domain-Format'
            ];
        }

        // Hole TLD
        $parts = explode('.', $domain);
        $tld = strtolower(end($parts));

        // Bekannte ungültige TLDs
        $invalidTlds = [
            'local',
            'internal',
            'test',
            'invalid',
            'example',
            'dev'  // Die alte .dev TLD, nicht Google's neue .dev
        ];

        if (in_array($tld, $invalidTlds)) {
            return [
                'valid' => false,
                'reason' => "Die TLD '.$tld' ist für WebAuthn nicht erlaubt"
            ];
        }

        // Liste gültiger TLDs (sollte regelmäßig aktualisiert werden)
        $validTlds = [
            // Generic TLDs
            'com', 'org', 'net', 'edu', 'gov', 'mil', 'int',
            // Country Code TLDs
            'de', 'uk', 'fr', 'it', 'es', 'nl', 'be', 'at', 'ch',
            // New gTLDs
            'io', 'dev', 'app', 'cloud', 'online', 'tech', 'site',
            // Fügen Sie weitere TLDs nach Bedarf hinzu
        ];

        if (!in_array($tld, $validTlds)) {
            return [
                'valid' => false,
                'reason' => "Unbekannte oder nicht unterstützte TLD '.$tld'"
            ];
        }

        return [
            'valid' => true,
            'reason' => 'Domain ist für WebAuthn geeignet'
        ];
    }

    /**
     * Prüft ob HTTPS für diese Domain erzwungen werden soll
     */
    public static function requiresHttps(string $domain): bool 
    {
        // Localhost darf HTTP verwenden
        if ($domain === 'localhost' || str_ends_with($domain, '.localhost')) {
            return false;
        }
        
        return true;
    }

    /**
     * Gibt eine benutzerfreundliche Empfehlung für WebAuthn
     */
    public static function getWebAuthnRecommendation(string $domain): string 
    {
        $result = self::validateForWebAuthn($domain);
        
        if (!$result['valid']) {
            if (filter_var($domain, FILTER_VALIDATE_IP)) {
                return "Für WebAuthn wird eine echte Domain benötigt. " .
                       "Bitte konfigurieren Sie einen Domainname anstelle der IP-Adresse {$domain}";
            }
            
            $parts = explode('.', $domain);
            $tld = end($parts);
            
            return "Die Domain {$domain} kann nicht für WebAuthn verwendet werden. " .
                   "Grund: {$result['reason']}\n" .
                   "Empfehlung: Verwenden Sie eine Domain mit einer offiziellen TLD " .
                   "wie .com, .org, .de etc.";
        }
        
        if ($domain === 'localhost' || str_ends_with($domain, '.localhost')) {
            return "Die Domain {$domain} ist nur für Entwicklungszwecke geeignet. " .
                   "Für Produktivumgebungen verwenden Sie bitte eine echte Domain.";
        }
        
        return "Die Domain {$domain} ist für WebAuthn geeignet.";
    }
}
