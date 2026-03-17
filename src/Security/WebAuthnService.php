<?php
namespace App\Security;

class WebAuthnService 
{
    private string $rpName;
    private ?string $rpId;
    private bool $enabled;
    
    public function __construct()
    {
        $config = require __DIR__ . '/../../config/config.php';
        $this->rpName = $config['application']['name'];
        
        // Validiere Domain und setze RP ID
        $domain = $config['application']['domain'];
        if (empty($domain)) {
            throw new \RuntimeException('application.domain muss in config.php konfiguriert sein');
        }
        
        // Prüfe Domain-Validität für WebAuthn
        $validation = DomainValidator::validateForWebAuthn($domain);
        
        if ($validation['valid']) {
            $this->rpId = $domain;
            $this->enabled = $config['application']['webauthn_enabled'] ?? true;
            
            // Prüfe HTTPS-Anforderung
            if (DomainValidator::requiresHttps($domain) && !$config['application']['force_https']) {
                throw new \RuntimeException(
                    "HTTPS ist für WebAuthn auf {$domain} erforderlich. " .
                    "Bitte setzen Sie 'force_https' in der Konfiguration auf true."
                );
            }
        } else {
            // Deaktiviere WebAuthn für ungültige Domains/TLDs
            $this->rpId = null;
            $this->enabled = false;
            
            // Log Warnung mit Empfehlung
            error_log(DomainValidator::getWebAuthnRecommendation($domain));
        }
    }
    
    /**
     * Prüft ob WebAuthn für diese Installation verfügbar ist
     */
    public function isAvailable(): bool
    {
        return $this->enabled && $this->rpId !== null;
    }
    
    
    /**
     * Generiert eine Challenge für die Registrierung eines neuen FIDO2 Authenticators
     * 
     * @return array<string, mixed>
     */
    public function generateRegistrationOptions(string $username, string $userId): array 
    {
        $challenge = random_bytes(32);
        
        return [
            'rp' => [
                'name' => $this->rpName,
                'id' => $this->rpId
            ],
            'user' => [
                'id' => base64_encode($userId),
                'name' => $username,
                'displayName' => $username
            ],
            'challenge' => base64_encode($challenge),
            'pubKeyCredParams' => [
                [
                    'type' => 'public-key',
                    'alg' => -8  // EdDSA (Ed25519)
                ],
                [
                    'type' => 'public-key',
                    'alg' => -7  // ES256
                ]
            ],
            'timeout' => 60000,
            'attestation' => 'direct',
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'cross-platform', // oder 'platform' für biometrische Sensoren
                'userVerification' => 'preferred',
                'requireResidentKey' => true  // Für passwordless login
            ]
        ];
    }
    
    /**
     * Verifiziert die Registrierungsantwort vom Authenticator
     * 
     * @param array<string, mixed> $credential
     * @return array<string, mixed>
     */
    public function verifyRegistration(array $credential): array 
    {
        // Hier würde die Verifikation der Attestation stattfinden
        // Dies beinhaltet:
        // 1. Überprüfung der Challenge
        // 2. Verifizierung der Attestation Signature
        // 3. Überprüfung des Attestation Zertifikats
        // 4. Extraktion des öffentlichen Schlüssels
        
        return [
            'credentialId' => $credential['id'],
            'publicKey' => $credential['publicKey'],
            'signCount' => $credential['signCount'],
            'attestationType' => $credential['attestationType']
        ];
    }
    
    /**
     * Generiert eine Challenge für die Authentifizierung
     * 
     * @param array<int, array<string, mixed>> $allowedCredentials
     * @return array<string, mixed>
     */
    public function generateAuthenticationOptions(array $allowedCredentials): array 
    {
        $challenge = random_bytes(32);
        
        return [
            'challenge' => base64_encode($challenge),
            'timeout' => 60000,
            'rpId' => $this->rpId,
            'allowCredentials' => array_map(function($credential) {
                return [
                    'type' => 'public-key',
                    'id' => $credential['credentialId']
                ];
            }, $allowedCredentials),
            'userVerification' => 'preferred'
        ];
    }
    
    /**
     * Verifiziert die Authentifizierungsantwort
     * 
     * @param array<string, mixed> $credential
     * @param array<string, mixed> $storedCredential
     */
    public function verifyAuthentication(
        array $credential,
        array $storedCredential,
        string $challenge
    ): bool {
        // Hier würde die Verifikation der Assertion stattfinden
        // Dies beinhaltet:
        // 1. Überprüfung der Challenge
        // 2. Verifizierung der Assertion Signature
        // 3. Überprüfung des Signaturzählers
        
        return true; // Platzhalter
    }
}
