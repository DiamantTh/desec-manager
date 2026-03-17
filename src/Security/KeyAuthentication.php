<?php
namespace App\Security;

class KeyAuthentication 
{
    private const KEY_CONTEXT = "DeSEC-Auth-2025-09";
    private const CHALLENGE_TIMEOUT = 300; // 5 Minuten
    
    /**
     * Generiert ein neues Ed448 Schlüsselpaar
     * 
     * @return array{private: string, public: string}
     */
    public static function generateKeyPair(): array 
    {
        $keypair = sodium_crypto_sign_keypair();
        
        return [
            'private' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
            'public' => base64_encode(sodium_crypto_sign_publickey($keypair))
        ];
    }
    
    /**
     * Generiert eine Challenge für den Login-Prozess
     * 
     * @return array{challenge: string, expires: int}
     */
    public function generateChallenge(): array 
    {
        $challenge = random_bytes(32);
        $expires = time() + self::CHALLENGE_TIMEOUT;
        
        // Format: base64(challenge):timestamp
        return [
            'challenge' => base64_encode($challenge),
            'expires' => $expires
        ];
    }
    
    /**
     * Verifiziert eine signierte Challenge
     */
    public function verifyChallenge(
        string $publicKey,
        string $challenge,
        int $expires,
        string $signature
    ): bool {
        if (time() > $expires) {
            return false;
        }
        
        try {
            $decodedKey = base64_decode($publicKey, true);
            $decodedChallenge = base64_decode($challenge, true);
            $decodedSignature = base64_decode($signature, true);
            
            // Prüfe auf gültige Dekodierung und nicht-leere Strings
            if ($decodedKey === false || $decodedKey === '' ||
                $decodedChallenge === false || $decodedChallenge === '' ||
                $decodedSignature === false || $decodedSignature === '') {
                return false;
            }
            
            // Erstelle den zu verifizierenden Message-String
            $message = sodium_bin2hex($decodedChallenge) . "|" . $expires . "|" . self::KEY_CONTEXT;
            
            // Verifiziere die Signatur
            $result = sodium_crypto_sign_verify_detached(
                $decodedSignature,
                $message,
                $decodedKey
            );
            
            return $result;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Signiert Daten mit einem privaten Schlüssel
     */
    public static function sign(string $privateKey, string $data): string 
    {
        $decodedKey = base64_decode($privateKey, true);
        if ($decodedKey === false || $decodedKey === '') {
            throw new \InvalidArgumentException('Invalid private key');
        }
        $signature = sodium_crypto_sign_detached($data, $decodedKey);
        return base64_encode($signature);
    }
    
    /**
     * Generiert einen deterministischen API Key aus einem privaten Schlüssel
     */
    public static function deriveApiKey(string $privateKey): string 
    {
        $decodedKey = base64_decode($privateKey, true);
        if ($decodedKey === false || $decodedKey === '') {
            throw new \InvalidArgumentException('Invalid private key');
        }
        $context = self::KEY_CONTEXT . "-API";
        
        // Verwende den privaten Schlüssel um einen deterministischen API Key zu generieren
        $apiKey = sodium_crypto_generichash(
            $context,
            $decodedKey,
            32 // 256-bit Output
        );
        
        return base64_encode($apiKey);
    }
}
