<?php
namespace App\Security;

class PasswordHasher {
    /** @var array{algo: string|int, options: array<string, int>} */
    private array $config;

    /**
     * @param array<string, int> $options Argon2id-Optionen (memory_cost, time_cost, threads).
     *                                    Wird vom DI-Container via TOML-Konfiguration injiziert.
     */
    public function __construct(array $options = []) {
        $this->config = [
            'algo' => PASSWORD_ARGON2ID,
            'options' => [
                'memory_cost' => (int)($options['memory_cost'] ?? 65536),
                'time_cost'   => (int)($options['time_cost']   ?? 4),
                'threads'     => (int)($options['threads']     ?? 2),
            ],
        ];
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
