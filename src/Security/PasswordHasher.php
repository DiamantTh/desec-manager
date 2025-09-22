<?php
namespace App\Security;

use App\Config\ConfigLoader;

class PasswordHasher
{
    private int $algorithm;

    /**
     * @var array<string, int>
     */
    private array $options;

    /**
     * @param array<string, mixed>|null $config
     */
    public function __construct(?array $config = null)
    {
        if ($config === null) {
            $config = ConfigLoader::load();
        }

        $argon2Config = $config['security']['argon2'] ?? null;
        if (!is_array($argon2Config)) {
            throw new \RuntimeException('Argon2 configuration missing');
        }

        $algorithm = $argon2Config['algorithm'] ?? 'argon2id';
        $algorithm = is_string($algorithm) ? strtolower($algorithm) : 'argon2id';
        $this->algorithm = $this->resolveAlgorithm($algorithm);

        $options = $argon2Config['options'] ?? [];
        $this->options = $this->normalizeOptions(is_array($options) ? $options : []);
    }

    public function hash(string $password): string
    {
        return password_hash($password, $this->algorithm, $this->options);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm, $this->options);
    }

    private function resolveAlgorithm(string $algorithm): int
    {
        return match ($algorithm) {
            'argon2i', 'password_argon2i' => PASSWORD_ARGON2I,
            'argon2d', 'password_argon2d' => defined('PASSWORD_ARGON2D') ? PASSWORD_ARGON2D : PASSWORD_ARGON2I,
            'argon2id', 'password_argon2id', '' => PASSWORD_ARGON2ID,
            default => throw new \RuntimeException(sprintf('Unsupported Argon2 algorithm "%s"', $algorithm)),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, int>
     */
    private function normalizeOptions(array $options): array
    {
        $allowed = ['memory_cost', 'time_cost', 'threads'];
        $normalized = [];

        foreach ($allowed as $option) {
            if (isset($options[$option])) {
                $normalized[$option] = (int) $options[$option];
            }
        }

        return $normalized;
    }
}
