<?php
namespace App\Security;

class EncryptionService 
{
    private string $key;
    
    public function __construct()
    {
        // Lade Encryption Key aus Konfiguration
        $config = require __DIR__ . '/../../config/config.php';
        if (empty($config['security']['encryption_key'])) {
            throw new \RuntimeException('Encryption key not configured');
        }
        $this->key = base64_decode($config['security']['encryption_key']);
    }
    
    public function encrypt(string $data): string 
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($data, $nonce, $this->key);
        $encoded = base64_encode($nonce . $cipher);
        sodium_memzero($data);
        return $encoded;
    }
    
    public function decrypt(string $encoded): string 
    {
        $decoded = base64_decode($encoded);
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $cipher = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed');
        }
        
        $data = $plain;
        sodium_memzero($plain);
        return $data;
    }
    
    public static function generateKey(): string 
    {
        return base64_encode(sodium_crypto_secretbox_keygen());
    }
}
