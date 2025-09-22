<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

if (!defined('PASSWORD_ARGON2I')) {
    define('PASSWORD_ARGON2I', 10_001);
}

if (!defined('PASSWORD_ARGON2ID')) {
    define('PASSWORD_ARGON2ID', 10_002);
}

final class PasswordHasherTest extends TestCase
{
    public function testItUsesModernConfigurationWhenAvailable(): void
    {
        $config = [
            'security' => [
                'argon2' => [
                    'algorithm' => 'argon2i',
                    'options' => [
                        'memory_cost' => 65536,
                        'time_cost' => 4,
                        'threads' => 2,
                    ],
                ],
                'algo' => constant('PASSWORD_ARGON2ID'),
                'options' => [
                    'memory_cost' => 1024,
                    'time_cost' => 1,
                    'threads' => 1,
                ],
            ],
        ];

        $hasher = new PasswordHasher($config);

        self::assertSame(constant('PASSWORD_ARGON2I'), $this->readProperty($hasher, 'algorithm'));
        self::assertSame(
            [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 2,
            ],
            $this->readProperty($hasher, 'options')
        );
    }

    public function testItFallsBackToLegacyConfiguration(): void
    {
        $config = [
            'security' => [
                'algo' => 'PASSWORD_ARGON2ID',
                'options' => [
                    'memory_cost' => 131072,
                    'time_cost' => 3,
                    'threads' => 1,
                    'ignored' => 'value',
                ],
            ],
        ];

        $hasher = new PasswordHasher($config);

        self::assertSame(constant('PASSWORD_ARGON2ID'), $this->readProperty($hasher, 'algorithm'));
        self::assertSame(
            [
                'memory_cost' => 131072,
                'time_cost' => 3,
                'threads' => 1,
            ],
            $this->readProperty($hasher, 'options')
        );
    }

    /**
     * @return mixed
     */
    private function readProperty(object $object, string $property)
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}
