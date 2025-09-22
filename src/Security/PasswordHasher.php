<?php
namespace App\Security;

class PasswordHasher {
    private array $config;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../../config/argon2.php';
    }
    
    public function hash(string $password): string {
        return password_hash($password, $this->config['algo'], $this->config['options']);
    }
    
    public function verify(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    public function needsRehash(string $hash): bool {
        return password_needs_rehash($hash, $this->config['algo'], $this->config['options']);
    }
}
