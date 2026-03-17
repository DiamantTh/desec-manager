<?php
namespace App\Security;

class PasswordHasher {
    /** @var array{algo: string|int, options: array<string, int>} */
    private array $config;

    public function __construct() {
        $defaults = [
            'algo' => PASSWORD_ARGON2ID,
            'options' => [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 2,
            ],
        ];

        $configPath = __DIR__ . '/../../config/config.php';
        if (file_exists($configPath)) {
            $appConfig = require $configPath;
            if (isset($appConfig['security']['algo'], $appConfig['security']['options'])) {
                $defaults['algo'] = $appConfig['security']['algo'];
                $defaults['options'] = array_merge($defaults['options'], $appConfig['security']['options']);
            }
        }

        $this->config = $defaults;
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
