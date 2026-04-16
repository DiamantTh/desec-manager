<?php

declare(strict_types=1);

/**
 * PHP-DI Container-Konfiguration für die Mezzio-Anwendung.
 *
 * Dieser Container dient als PSR-11-Implementierung für Mezzio.
 * PHP-DI ist direkt kompatibel — kein Slim-Bridge-Paket nötig.
 *
 * Verwendung in public/index.php:
 *   $container = require __DIR__ . '/../app/container.php';
 */

use App\Config\TomlLoader;
use App\Handler\AdminHandler;
use App\Handler\AuthHandler;
use App\Handler\DashboardHandler;
use App\Handler\DomainHandler;
use App\Handler\HomeHandler;
use App\Handler\KeyHandler;
use App\Handler\ProfileHandler;
use App\Handler\RecordHandler;
use App\Handler\TotpApiHandler;
use App\Handler\WebAuthnApiHandler;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SentryMiddleware;
use App\Middleware\SessionContextMiddleware;
use App\Middleware\SessionMiddleware;
use App\Repository\ApiKeyRepository;
use App\Repository\DomainRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Service\SystemHealthService;
use App\Command\CacheClearCommand;
use App\Command\DbMigrateCommand;
use App\Command\I18nCompileCommand;
use App\Command\PasswordGenerateCommand;
use App\Command\UserCreateCommand;
use App\Security\EncryptionService;
use App\Security\PasswordGenerator;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;
use App\Security\TlsDetector;
use App\Security\TotpService;
use App\Security\UserKeyManager;
use App\Security\WebAuthnService;
use App\Service\DeSECProxyService;
use App\Service\DNSService;
use App\Service\ThemeManager;
use App\Service\AuthorizationService;
use App\Session\SessionContext;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Laminas\Diactoros\UriFactory;
use Mezzio\Application;
use Mezzio\Container\ApplicationFactory;
use Mezzio\Container\MiddlewareFactoryFactory;
use Mezzio\Container\NotFoundHandlerFactory;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\FastRouteRouter;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\DispatchMiddlewareFactory;
use Mezzio\Router\Middleware\RouteMiddleware;
use Mezzio\Router\Middleware\RouteMiddlewareFactory;
use Mezzio\Router\RouterInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use App\Clock\SystemClock;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use App\Repository\DomainTagRepository;
use App\Repository\SettingsRepository;
use Mezzio\Csrf\CsrfMiddleware;
use Mezzio\Csrf\CsrfMiddlewareFactory;
use Mezzio\Csrf\SessionCsrfGuardFactory;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

// --- Konfiguration aus TOML-Dateien laden ---
$tomlLoader = new TomlLoader(dirname(__DIR__) . '/config');
$appConfig  = $tomlLoader->load();

// --- Container aufbauen ---
$builder = new ContainerBuilder();

$builder->addDefinitions([

    // -------------------------------------------------------------------------
    // Konfiguration
    // -------------------------------------------------------------------------
    'config' => $appConfig,

    // -------------------------------------------------------------------------
    // PSR-7 / PSR-17 (Laminas Diactoros — Referenzimplementierung)
    // -------------------------------------------------------------------------
    ResponseFactoryInterface::class      => DI\create(ResponseFactory::class),
    ServerRequestFactoryInterface::class => DI\create(ServerRequestFactory::class),
    StreamFactoryInterface::class        => DI\create(StreamFactory::class),
    UploadedFileFactoryInterface::class  => DI\create(UploadedFileFactory::class),
    UriFactoryInterface::class           => DI\create(UriFactory::class),

    // -------------------------------------------------------------------------
    // PSR-18 HTTP Client (GuzzleHttp als Implementierung)
    // -------------------------------------------------------------------------
    ClientInterface::class => DI\create(\GuzzleHttp\Client::class),

    // -------------------------------------------------------------------------
    // PSR-20 Clock (SystemClock — testbare Zeitstempel statt date()/new DateTimeImmutable())
    // -------------------------------------------------------------------------
    ClockInterface::class => DI\create(SystemClock::class),

    // -------------------------------------------------------------------------
    // Router (FastRoute via mezzio-fastroute)
    // -------------------------------------------------------------------------
    RouterInterface::class => DI\factory(function (): FastRouteRouter {
        return new FastRouteRouter();
    }),

    // -------------------------------------------------------------------------
    // Mezzio Application + Infrastruktur
    // -------------------------------------------------------------------------
    Application::class => DI\factory(function (ContainerInterface $c): Application {
        return (new ApplicationFactory())($c);
    }),

    MiddlewareFactory::class => DI\factory(function (ContainerInterface $c): \Mezzio\MiddlewareFactoryInterface {
        return (new MiddlewareFactoryFactory())($c);
    }),

    RouteMiddleware::class => DI\factory(function (ContainerInterface $c): RouteMiddleware {
        return (new RouteMiddlewareFactory())($c);
    }),

    DispatchMiddleware::class => DI\factory(function (ContainerInterface $c): DispatchMiddleware {
        return (new DispatchMiddlewareFactory())($c);
    }),

    NotFoundHandler::class => DI\factory(function (ContainerInterface $c): NotFoundHandler {
        return (new NotFoundHandlerFactory())($c);
    }),

    // -------------------------------------------------------------------------
    // Datenbankverbindung (Doctrine DBAL)
    // -------------------------------------------------------------------------
    Connection::class => DI\factory(function (ContainerInterface $c): Connection {
        /** @var array<string, mixed> $cfg */
        $cfg = $c->get('config')['database'] ?? [];

        if (($cfg['driver'] ?? '') === 'pdo_sqlite') {
            $params = [
                'driver' => 'pdo_sqlite',
                'path'   => dirname(__DIR__) . '/' . ($cfg['sqlite']['path'] ?? 'var/database.sqlite'),
            ];
        } else {
            $params = [
                'driver'   => $cfg['driver']   ?? 'pdo_mysql',
                'host'     => $cfg['host']     ?? 'localhost',
                'port'     => (int)($cfg['port'] ?? 3306),
                'dbname'   => $cfg['name']     ?? '',
                'user'     => $cfg['user']     ?? '',
                'password' => $cfg['password'] ?? '',
                'charset'  => $cfg['charset']  ?? 'utf8mb4',
            ];
        }

        return DriverManager::getConnection($params);
    }),

    // -------------------------------------------------------------------------
    // Logging (Monolog PSR-3)
    // -------------------------------------------------------------------------
    LoggerInterface::class => DI\factory(function (ContainerInterface $c): LoggerInterface {
        /** @var array<string, mixed> $cfg */
        $cfg   = $c->get('config');
        $debug = (bool)($cfg['app']['debug'] ?? false);

        $logDir = dirname(__DIR__) . '/var/log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logger = new Logger('desec-manager');
        $logger->pushHandler(
            new StreamHandler(
                $logDir . '/app.log',
                $debug ? Level::Debug : Level::Info
            )
        );

        return $logger;
    }),

    // -------------------------------------------------------------------------
    // Mail (Symfony Mailer — Konfiguration via Admin-Interface / DB)
    // Passwort ausschließlich via MAIL_PASSWORD-Umgebungsvariable.
    // -------------------------------------------------------------------------
    MailerInterface::class => DI\factory(function (ContainerInterface $c): MailerInterface {
        $settings  = $c->get(SettingsRepository::class);
        $transport = $settings->getString('mail.transport', 'smtps');

        if (str_contains($transport, '://')) {
            // Vollständiger DSN (smtps://…, smtp://…, Cloud-API etc.)
            $dsn = $transport;
        } else {
            // transport = "smtps" | "smtp" → Einzelfelder aus DB
            $host   = $settings->getString('mail.smtp.host', 'localhost');
            $port   = $settings->getInt('mail.smtp.port', 465);
            $user   = rawurlencode($settings->getString('mail.smtp.username'));
            $pass   = rawurlencode((string)(getenv('MAIL_PASSWORD') ?: ''));
            $scheme = ($transport === 'smtp') ? 'smtp' : 'smtps';

            $dsn = ($user !== '' && $pass !== '')
                ? "{$scheme}://{$user}:{$pass}@{$host}:{$port}"
                : "{$scheme}://{$host}:{$port}";
        }

        return new Mailer(Transport::fromDsn($dsn));
    }),

    // -------------------------------------------------------------------------
    // Sicherheitsdienste
    // -------------------------------------------------------------------------
    EncryptionService::class => DI\factory(function (ContainerInterface $c): EncryptionService {
        /** @var array<string, mixed> $cfg */
        $cfg = $c->get('config');
        $key = (string)($cfg['security']['encryption_key'] ?? '');

        if ($key === '') {
            throw new \RuntimeException(
                'security.encryption_key ist nicht gesetzt. ' .
                'Setze ENCRYPTION_KEY-Umgebungsvariable oder trage den Key in config.local.toml ein.'
            );
        }

        return new EncryptionService($key);
    }),

    PasswordHasher::class => DI\factory(function (ContainerInterface $c): PasswordHasher {
        /** @var array<string, mixed> $cfg */
        $cfg = $c->get('config')['security']['password'] ?? [];
        return new PasswordHasher([
            'memory_cost' => (int)($cfg['memory_cost'] ?? 65536),
            'time_cost'   => (int)($cfg['time_cost']   ?? 4),
            'threads'     => (int)($cfg['threads']     ?? 2),
        ]);
    }),

    PasswordPolicy::class => DI\factory(function (ContainerInterface $c): PasswordPolicy {
        /** @var array<string, mixed> $cfg */
        $cfg = $c->get('config')['security']['password'] ?? [];
        return new PasswordPolicy(
            (int)($cfg['min_length'] ?? 16),
            (int)($cfg['min_score']  ?? 0),
        );
    }),

    WebAuthnService::class => DI\factory(function (ContainerInterface $c): WebAuthnService {
        /** @var array<string, mixed> $cfg */
        $cfg = $c->get('config');
        return new WebAuthnService($cfg, $c->get(SessionContext::class));
    }),

    TotpService::class => DI\factory(function (ContainerInterface $c): TotpService {
        /** @var array<string, mixed> $cfg */
        $cfg = $c->get('config');
        return new TotpService($cfg);
    }),

    UserKeyManager::class => DI\autowire(),

    // -------------------------------------------------------------------------
    // PSR-16 Cache (symfony/cache)
    // Adapter wird aus config.toml [cache].adapter ermittelt.
    // -------------------------------------------------------------------------
    CacheInterface::class => DI\factory(function (ContainerInterface $c): CacheInterface {
        /** @var array<string, mixed> $cfg */
        $cfg       = $c->get('config')['cache'] ?? [];
        $adapter   = (string)($cfg['adapter']   ?? 'filesystem');
        $namespace = (string)($cfg['namespace'] ?? 'desec');
        $ttl       = (int)($cfg['ttl']          ?? 3600);

        $psr6 = match ($adapter) {
            'apcu' => new ApcuAdapter($namespace, $ttl),

            'redis' => (static function () use ($cfg, $namespace, $ttl): RedisAdapter {
                $redisCfg = $cfg['redis'] ?? [];
                $dsn      = sprintf(
                    'redis://%s%s:%d/%d',
                    ($redisCfg['password'] ?? '') !== '' ? ':' . $redisCfg['password'] . '@' : '',
                    $redisCfg['host']     ?? '127.0.0.1',
                    (int)($redisCfg['port']     ?? 6379),
                    (int)($redisCfg['database'] ?? 0),
                );
                return new RedisAdapter(RedisAdapter::createConnection($dsn), $namespace, $ttl);
            })(),

            'memcached' => (static function () use ($cfg, $namespace, $ttl): MemcachedAdapter {
                $mCfg = $cfg['memcached'] ?? [];
                $dsn  = sprintf('memcached://%s:%d', $mCfg['host'] ?? '127.0.0.1', (int)($mCfg['port'] ?? 11211));
                return new MemcachedAdapter(MemcachedAdapter::createConnection($dsn), $namespace, $ttl);
            })(),

            default => new FilesystemAdapter(
                $namespace,
                $ttl,
                dirname(__DIR__) . '/' . ($cfg['filesystem']['path'] ?? 'var/cache')
            ),
        };

        return new Psr16Cache($psr6);
    }),

    // -------------------------------------------------------------------------
    // Repositories (Doctrine DBAL, kein ORM)
    // -------------------------------------------------------------------------
    UserRepository::class               => DI\autowire(),
    ApiKeyRepository::class             => DI\autowire(),
    DomainRepository::class             => DI\autowire(),
    DomainTagRepository::class          => DI\autowire(),
    SettingsRepository::class           => DI\autowire(),
    WebAuthnCredentialRepository::class => DI\autowire(),

    // -------------------------------------------------------------------------
    // Anwendungsdienste
    // -------------------------------------------------------------------------
    DNSService::class          => DI\autowire(),
    DeSECProxyService::class   => DI\autowire(),
    SystemHealthService::class => DI\autowire(),

    // -------------------------------------------------------------------------
    // Middleware
    // -------------------------------------------------------------------------
    SecurityHeadersMiddleware::class => DI\create(SecurityHeadersMiddleware::class),

    SessionMiddleware::class => DI\factory(function (ContainerInterface $c): SessionMiddleware {
        return new SessionMiddleware($c->get('config'));
    }),

    // SessionContext: Request-Scoped Session-Wrapper (Singleton im Container)
    SessionContext::class => DI\create(SessionContext::class),

    // mezzio/mezzio-session: PhpSessionPersistence + SessionMiddleware
    \Mezzio\Session\Ext\PhpSessionPersistence::class => DI\factory(function (): \Mezzio\Session\Ext\PhpSessionPersistence {
        return new \Mezzio\Session\Ext\PhpSessionPersistence();
    }),

    \Mezzio\Session\SessionMiddleware::class => DI\factory(function (ContainerInterface $c): \Mezzio\Session\SessionMiddleware {
        return new \Mezzio\Session\SessionMiddleware(
            $c->get(\Mezzio\Session\Ext\PhpSessionPersistence::class)
        );
    }),

    // laminas/laminas-i18n: Translator-Instanz für SessionContextMiddleware
    \Laminas\I18n\Translator\Translator::class => DI\factory(function (): \Laminas\I18n\Translator\Translator {
        $localeDir  = dirname(__DIR__) . '/locale';
        $translator = new \Laminas\I18n\Translator\Translator();
        $translator->addTranslationFilePattern(
            'gettext',
            $localeDir,
            '%s/LC_MESSAGES/' . \App\Service\Translator::DOMAIN . '.mo',
            \App\Service\Translator::DOMAIN,
        );
        $translator->setLocale('en_US');
        return $translator;
    }),

    // SessionContextMiddleware: autowired (braucht SessionContext + Laminas\Translator)
    SessionContextMiddleware::class => DI\autowire(),

    // SentryMiddleware: nur aktiv wenn sentry.dsn in der Konfiguration gesetzt
    SentryMiddleware::class => DI\factory(function (ContainerInterface $c): SentryMiddleware {
        return new SentryMiddleware($c->get('config'));
    }),

    // mezzio/mezzio-csrf: Guard-Factory + Middleware
    // CsrfMiddleware liest den Guard-Typ aus der Konfiguration:
    //   security.csrf.strategy = "session" → SessionCsrfGuard
    //   security.csrf.strategy = "flash"   → FlashCsrfGuard
    'Mezzio\Csrf\CsrfGuardFactoryInterface' => DI\factory(function (ContainerInterface $c): \Mezzio\Csrf\CsrfGuardFactoryInterface {
        $strategy = (string)(($c->get('config')['security']['csrf']['strategy'] ?? 'session'));
        return $strategy === 'flash'
            ? new \Mezzio\Csrf\FlashCsrfGuardFactory()
            : new SessionCsrfGuardFactory();
    }),

    CsrfMiddleware::class => DI\factory(function (ContainerInterface $c): CsrfMiddleware {
        $attribute = (string)(($c->get('config')['security']['csrf']['attribute'] ?? '__csrf'));
        return new CsrfMiddleware(
            $c->get('Mezzio\Csrf\CsrfGuardFactoryInterface'),
            $attribute
        );
    }),

    AuthMiddleware::class => DI\autowire(),

    // -------------------------------------------------------------------------
    // TlsDetector — erkennt HTTPS direkt und hinter Proxy
    // -------------------------------------------------------------------------
    TlsDetector::class => DI\factory(function (ContainerInterface $c): TlsDetector {
        return new TlsDetector($c->get('config'));
    }),

    // SessionRepository — Autowire: Connection + ClockInterface sind registriert
    SessionRepository::class => DI\autowire(),

    // -------------------------------------------------------------------------
    // Rate-Limiting (PSR-16, per Aktion)
    // Key: rate_limit:{action}:{sha256(ip)}
    // Verwendung in routes.php: ['rate_limit.login', SomeHandler::class]
    // -------------------------------------------------------------------------
    'rate_limit.login' => DI\factory(function (ContainerInterface $c): RateLimitMiddleware {
        $cfg = $c->get('config')['security']['rate_limit']['login'] ?? [];
        return new RateLimitMiddleware($c->get(\Psr\SimpleCache\CacheInterface::class), 'login', $cfg);
    }),
    'rate_limit.form' => DI\factory(function (ContainerInterface $c): RateLimitMiddleware {
        $cfg = $c->get('config')['security']['rate_limit']['form'] ?? [];
        return new RateLimitMiddleware($c->get(\Psr\SimpleCache\CacheInterface::class), 'form', $cfg);
    }),
    'rate_limit.domain' => DI\factory(function (ContainerInterface $c): RateLimitMiddleware {
        $cfg = $c->get('config')['security']['rate_limit']['domain'] ?? [];
        return new RateLimitMiddleware($c->get(\Psr\SimpleCache\CacheInterface::class), 'domain', $cfg);
    }),
    'rate_limit.key' => DI\factory(function (ContainerInterface $c): RateLimitMiddleware {
        $cfg = $c->get('config')['security']['rate_limit']['key'] ?? [];
        return new RateLimitMiddleware($c->get(\Psr\SimpleCache\CacheInterface::class), 'key', $cfg);
    }),
    'rate_limit.totp' => DI\factory(function (ContainerInterface $c): RateLimitMiddleware {
        $cfg = $c->get('config')['security']['rate_limit']['totp'] ?? [];
        return new RateLimitMiddleware($c->get(\Psr\SimpleCache\CacheInterface::class), 'totp', $cfg);
    }),

    // -------------------------------------------------------------------------
    // PSR-15-Handler
    // -------------------------------------------------------------------------
    HomeHandler::class => DI\autowire(),

    AuthHandler::class => DI\factory(function (ContainerInterface $c): AuthHandler {
        return new AuthHandler(
            $c->get(ThemeManager::class),
            $c->get(SessionContext::class),
            $c->get(AuthorizationService::class),
            $c->get(UserRepository::class),
            $c->get(WebAuthnCredentialRepository::class),
            $c->get(TotpService::class),
            $c->get(PasswordHasher::class),
            $c->get(UserKeyManager::class),
            $c->get(SessionRepository::class),
            $c->get(TlsDetector::class),
            $c->get('config'),
        );
    }),

    DashboardHandler::class   => DI\autowire(),
    DomainHandler::class      => DI\autowire(),
    RecordHandler::class      => DI\autowire(),
    KeyHandler::class         => DI\autowire(),
    AdminHandler::class       => DI\autowire(),
    ProfileHandler::class     => DI\autowire(),
    WebAuthnApiHandler::class => DI\factory(function (ContainerInterface $c): WebAuthnApiHandler {
        return new WebAuthnApiHandler(
            $c->get(ThemeManager::class),
            $c->get(SessionContext::class),
            $c->get(AuthorizationService::class),
            $c->get(\App\Security\WebAuthnService::class),
            $c->get(WebAuthnCredentialRepository::class),
            $c->get(UserRepository::class),
            $c->get(UserKeyManager::class),
            $c->get(SessionRepository::class),
            $c->get(TlsDetector::class),
            $c->get('config'),
        );
    }),
    TotpApiHandler::class     => DI\autowire(),

    ThemeManager::class => DI\factory(function (ContainerInterface $c): ThemeManager {
        /** @var array<string, mixed> $cfg */
        $cfg         = $c->get('config');
        $projectRoot = dirname(__DIR__);

        // Volle Config übergeben: ThemeManager liest theme.name + application.name
        return new ThemeManager($cfg, $projectRoot);
    }),

    AuthorizationService::class => DI\autowire(),

    PasswordGenerator::class => DI\create(PasswordGenerator::class),

    // -------------------------------------------------------------------------
    // CLI-Commands (symfony/console)
    // -------------------------------------------------------------------------
    CacheClearCommand::class       => DI\autowire(),
    I18nCompileCommand::class      => DI\autowire(),
    UserCreateCommand::class       => DI\autowire(),
    PasswordGenerateCommand::class => DI\autowire(),
    DbMigrateCommand::class   => DI\factory(function (ContainerInterface $c): DbMigrateCommand {
        return new DbMigrateCommand($c->get(Connection::class), $c->get('config'));
    }),
]);

return $builder->build();
