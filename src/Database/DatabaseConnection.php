<?php
namespace App\Database;

use App\Config\ConfigLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class DatabaseConnection
{
    private static ?Connection $connection = null;
    private static ?array $config = null;

    public static function bootstrap(array $appConfig): void
    {
        self::$config = $appConfig['database'] ?? [];
        self::$connection = null;
    }

    public static function getConnection(): Connection
    {
        if (self::$config === null) {
            try {
                $config = ConfigLoader::load();
            } catch (\RuntimeException $exception) {
                throw new \RuntimeException('Database configuration missing. Run install.php first.', 0, $exception);
            }
            self::bootstrap($config);
        }

        if (self::$connection === null) {
            $driver = self::$config['driver'] ?? 'pdo_mysql';
            $connectionParams = [
                'driver' => $driver,
                'charset' => self::$config['charset'] ?? 'utf8mb4',
            ];

            if ($driver === 'pdo_sqlite') {
                $connectionParams['path'] = self::$config['path'] ?? __DIR__ . '/../../var/database.sqlite';
            } else {
                $connectionParams['host'] = self::$config['host'] ?? 'localhost';
                $connectionParams['dbname'] = self::$config['name'] ?? '';
                $connectionParams['user'] = self::$config['user'] ?? '';
                $connectionParams['password'] = self::$config['pass'] ?? '';
                if (!empty(self::$config['port'])) {
                    $connectionParams['port'] = (int) self::$config['port'];
                }
                $connectionParams['defaultTableOptions'] = [
                    'charset' => $connectionParams['charset'],
                    'collate' => self::$config['collation'] ?? 'utf8mb4_unicode_ci',
                ];
            }

            self::$connection = DriverManager::getConnection($connectionParams);
        }

        return self::$connection;
    }
}
