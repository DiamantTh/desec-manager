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

        $securityConfig = $config['security'] ?? null;
        if (!is_array($securityConfig)) {
            throw new \RuntimeException('Argon2 configuration missing');
        }

        $argon2Config = $securityConfig['argon2'] ?? null;
        if (is_array($argon2Config)) {
            $this->initializeFromArgon2Config($argon2Config);

            return;
        }

        if (array_key_exists('algo', $securityConfig) || array_key_exists('options', $securityConfig)) {
            $this->initializeFromLegacyConfig($securityConfig);

            return;
        }

        throw new \RuntimeException('Argon2 configuration missing');
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
            'argon2i', 'password_argon2i' => $this->requireAlgorithmConstant('PASSWORD_ARGON2I'),
            'argon2d', 'password_argon2d' => defined('PASSWORD_ARGON2D')
                ? $this->requireAlgorithmConstant('PASSWORD_ARGON2D')
                : $this->requireAlgorithmConstant('PASSWORD_ARGON2I'),
            'argon2id', 'password_argon2id', '' => $this->requireAlgorithmConstant('PASSWORD_ARGON2ID'),
            default => throw new \RuntimeException(sprintf('Unsupported Argon2 algorithm "%s"', $algorithm)),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function initializeFromArgon2Config(array $config): void
    {
        $algorithm = $config['algorithm'] ?? 'argon2id';
        $algorithm = is_string($algorithm) ? strtolower($algorithm) : 'argon2id';
        $this->algorithm = $this->resolveAlgorithm($algorithm);

        $options = $config['options'] ?? [];
        $this->options = $this->normalizeOptions(is_array($options) ? $options : []);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function initializeFromLegacyConfig(array $config): void
    {
        $algorithm = $config['algo'] ?? null;
        if (is_string($algorithm)) {
            $algorithm = strtolower($algorithm);
            $this->algorithm = $this->resolveAlgorithm($algorithm);
        } elseif (is_int($algorithm)) {
            $this->algorithm = $this->validateAlgorithmConstant($algorithm);
        } else {
            throw new \RuntimeException('Unsupported Argon2 algorithm configuration');
        }

        $options = $config['options'] ?? [];
        $this->options = $this->normalizeOptions(is_array($options) ? $options : []);
    }

    private function validateAlgorithmConstant(int $algorithm): int
    {
        $supportedAlgorithms = [
            $this->requireAlgorithmConstant('PASSWORD_ARGON2I'),
            $this->requireAlgorithmConstant('PASSWORD_ARGON2ID'),
        ];

        if (defined('PASSWORD_ARGON2D')) {
            $supportedAlgorithms[] = $this->requireAlgorithmConstant('PASSWORD_ARGON2D');
        }

        if (!in_array($algorithm, $supportedAlgorithms, true)) {
            throw new \RuntimeException(sprintf('Unsupported Argon2 algorithm "%s"', (string) $algorithm));
        }

        return $algorithm;
    }

    private function requireAlgorithmConstant(string $constantName): int
    {
        if (!defined($constantName)) {
            throw new \RuntimeException(sprintf('Unsupported Argon2 algorithm "%s"', strtolower($constantName)));
        }

        $value = constant($constantName);
        if (!is_int($value)) {
            throw new \RuntimeException(sprintf('Invalid Argon2 algorithm constant "%s"', $constantName));
        }

        return $value;
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
