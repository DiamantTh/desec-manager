<?php
/**
 * Installation script for deSEC Manager
 * Creates database, tables and initial admin user
 */

require_once __DIR__ . '/vendor/autoload.php';

// Prüfe PHP-Version und Erweiterungen
$requiredExtensions = ['pdo_mysql', 'sodium', 'openssl'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    die("Fehlende PHP-Erweiterungen: " . implode(', ', $missingExtensions) . "\n");
}

if (version_compare(PHP_VERSION, '8.1.0') < 0) {
    die("PHP 8.1 oder höher wird benötigt. Aktuelle Version: " . PHP_VERSION . "\n");
}

// Prüfe Schreibrechte
$requiredPaths = [
    __DIR__ . '/config',
];

foreach ($requiredPaths as $path) {
    if (!file_exists($path)) {
        if (!@mkdir($path, 0755, true)) {
            die("Konnte Verzeichnis nicht erstellen: {$path}\n");
        }
    } elseif (!is_writable($path)) {
        die("Keine Schreibrechte für: {$path}\n");
    }
}

// Konfigurationsvariablen
$config = [
    'db_host' => 'localhost',
    'db_name' => 'desec_manager',
    'admin_user' => [
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password' => null // wird später generiert
    ]
];

// Standard-Argon2id Parameter
$securityConfig = [
    'algo' => PASSWORD_ARGON2ID,
    'options' => [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 2,
    ],
];
$argon2_options = $securityConfig['options'];

// Generiere Encryption Key für API-Keys
require_once __DIR__ . '/vendor/autoload.php';
$encryptionKey = \App\Security\EncryptionService::generateKey();

// Hilfsfunktionen
function generateSecurePassword($length = 16): string {
    return bin2hex(random_bytes($length));
}

function validatePassword(string $password): bool {
    return strlen($password) >= 12 &&   // Mindestlänge
           preg_match('/[A-Z]/', $password) && // Großbuchstaben
           preg_match('/[a-z]/', $password) && // Kleinbuchstaben
           preg_match('/[0-9]/', $password) && // Zahlen
           preg_match('/[^A-Za-z0-9]/', $password); // Sonderzeichen
}

function createConfigFile(array $config, string $dbUser, string $dbPass): void {
    global $encryptionKey;

    $configFile = __DIR__ . '/config/config.php';
    $distFile = __DIR__ . '/config/config.php.dist';
    
    if (!file_exists($distFile)) {
        die("config.php.dist Template nicht gefunden!\n");
    }
    
    // Backup erstellen falls Datei existiert
    if (file_exists($configFile)) {
        $backup = $configFile . '.bak.' . date('Y-m-d-H-i-s');
        copy($configFile, $backup);
        echo "Backup der existierenden Konfiguration erstellt: {$backup}\n";
    }
    
    // Lade das Template
    $baseConfig = require $distFile;
    
    // Aktualisiere die Datenbank-Konfiguration
    $baseConfig['database']['host'] = $config['db_host'];
    $baseConfig['database']['name'] = $config['db_name'];
    $baseConfig['database']['user'] = $dbUser;
    $baseConfig['database']['pass'] = $dbPass;
    
    // Füge Encryption Key zur Konfiguration hinzu
    $baseConfig['security']['encryption_key'] = $encryptionKey;
    
    // Exportiere die Konfiguration
    $content = "<?php\nreturn " . var_export($baseConfig, true) . ";\n";
    
    if (!file_put_contents($configFile, $content)) {
        throw new Exception("Konnte Konfigurationsdatei nicht schreiben: {$configFile}");
    }
    
    // Setze sichere Berechtigungen
    chmod($configFile, 0600);
}

// Start Installation
echo "Starting deSEC Manager Installation...\n\n";

try {
    // 1. Verbindung zur MySQL als root
    echo "Please enter MySQL root password: ";
    system('stty -echo');
    $input = fgets(STDIN);
    $rootPassword = $input !== false ? trim($input) : '';
    system('stty echo');
    echo "\n";

    $rootPdo = new PDO(
        "mysql:host={$config['db_host']}", 
        'root', 
        $rootPassword
    );
    $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Erstelle Datenbank und Benutzer
    $dbUser = 'desec_user';
    $dbPass = generateSecurePassword();
    
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $rootPdo->exec("CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPass}'");
    $rootPdo->exec("GRANT ALL PRIVILEGES ON `{$config['db_name']}`.* TO '{$dbUser}'@'localhost'");
    $rootPdo->exec("FLUSH PRIVILEGES");

    echo "Database and user created successfully.\n";

    // 3. Verbinde als neuer Benutzer
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']}", 
        $dbUser, 
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 4. Erstelle Tabellen mit DBAL
    $connectionParams = [
        'dbname' => $config['db_name'],
        'user' => $dbUser,
        'password' => $dbPass,
        'host' => $config['db_host'],
        'driver' => 'pdo_mysql',
        'charset' => 'utf8mb4'
    ];

    $connection = \Doctrine\DBAL\DriverManager::getConnection($connectionParams);
    $schema = new \Doctrine\DBAL\Schema\Schema();

    // Users Tabelle
    $usersTable = $schema->createTable('users');
    $usersTable->addColumn('id', 'integer', ['autoincrement' => true]);
    $usersTable->addColumn('username', 'string', ['length' => 255]);
    $usersTable->addColumn('password_hash', 'string', ['length' => 255]);
    $usersTable->addColumn('email', 'string', ['length' => 255]);
    $usersTable->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
    $usersTable->addColumn('last_login', 'datetime', ['notnull' => false]);
    $usersTable->addColumn('is_active', 'boolean', ['default' => true]);
    $usersTable->addColumn('is_admin', 'boolean', ['default' => false]);
    $usersTable->setPrimaryKey(['id']);
    $usersTable->addUniqueIndex(['username']);
    $usersTable->addUniqueIndex(['email']);

    // API Keys Tabelle
    $apiKeysTable = $schema->createTable('api_keys');
    $apiKeysTable->addColumn('id', 'integer', ['autoincrement' => true]);
    $apiKeysTable->addColumn('user_id', 'integer');
    $apiKeysTable->addColumn('name', 'string', ['length' => 255]);
    $apiKeysTable->addColumn('api_key', 'string', ['length' => 255]);
    $apiKeysTable->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
    $apiKeysTable->addColumn('last_used', 'datetime', ['notnull' => false]);
    $apiKeysTable->addColumn('is_active', 'boolean', ['default' => true]);
    $apiKeysTable->setPrimaryKey(['id']);
    $apiKeysTable->addForeignKeyConstraint('users', ['user_id'], ['id'], 
        ['onDelete' => 'CASCADE']
    );

    // Domains Tabelle
    $domainsTable = $schema->createTable('domains');
    $domainsTable->addColumn('id', 'integer', ['autoincrement' => true]);
    $domainsTable->addColumn('user_id', 'integer');
    $domainsTable->addColumn('domain_name', 'string', ['length' => 255]);
    $domainsTable->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
    $domainsTable->setPrimaryKey(['id']);
    $domainsTable->addForeignKeyConstraint('users', ['user_id'], ['id'],
        ['onDelete' => 'CASCADE']
    );
    $domainsTable->addUniqueIndex(['domain_name']);

    // Schema ausführen
    $platform = $connection->getDatabasePlatform();
    $queries = $schema->toSql($platform);
    
    foreach ($queries as $query) {
        $connection->executeStatement($query);
    }

    echo "Tables created successfully.\n";

    // 5. Erstelle Admin-Benutzer
    do {
        $adminPass = generateSecurePassword(16); // Längeres Passwort
    } while (!validatePassword($adminPass));
    
    $config['admin_user']['password'] = $adminPass;

    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password_hash, email, is_admin) VALUES (?, ?, ?, TRUE)"
    );
    $stmt->execute([
        $config['admin_user']['username'],
        password_hash($adminPass, $securityConfig['algo'], $argon2_options),
        $config['admin_user']['email']
    ]);

    echo "Admin user created successfully.\n";

    // 6. Erstelle Konfigurationsdatei
    if (!is_dir(__DIR__ . '/config')) {
        mkdir(__DIR__ . '/config');
    }
    createConfigFile($config, $dbUser, $dbPass);

    echo "Configuration file created successfully.\n\n";

    // 7. Ausgabe der Zugangsdaten
    echo "Installation completed successfully!\n";
    echo "----------------------------------------\n";
    echo "Database User: {$dbUser}\n";
    echo "Database Password: {$dbPass}\n";
    echo "----------------------------------------\n";
    echo "Admin Username: {$config['admin_user']['username']}\n";
    echo "Admin Password: {$adminPass}\n";
    echo "----------------------------------------\n";
    echo "Please save these credentials securely and delete this file after installation!\n";

} catch (Exception $e) {
    die("Installation failed: " . $e->getMessage() . "\n");
}
