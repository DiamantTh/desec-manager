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
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SentryMiddleware;
use App\Middleware\SessionContextMiddleware;
use App\Middleware\SessionMiddleware;
use App\Repository\ApiKeyRepository;
use App\Repository\DomainRepository;
use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Service\SystemHealthService;
use App\Security\EncryptionService;
use App\Security\PasswordHasher;
use App\Security\TotpService;
use App\Security\UserKeyManager;
use App\Security\WebAuthnService;
use App\Service\DeSECProxyService;
use App\Service\DNSService;
use App\Service\ThemeManager;
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
    // Mail (Symfony Mailer)
    // -------------------------------------------------------------------------
    MailerInterface::class => DI\factory(function (ContainerInterface $c): MailerInterface {
        /** @var array<string, mixed> $mailCfg */
        $mailCfg  = $c->get('config')['mail'] ?? [];
        $transport = $mailCfg['transport'] ?? 'smtp';

        if (str_contains($transport, '://')) {
            // Vollständiger DSN (z.B. "ses+smtp://key:secret@default")
            $dsn = $transport;
        } else {
            $smtp = $mailCfg['smtp'] ?? [];
            $user = rawurlencode((string)($smtp['username'] ?? ''));
            $pass = rawurlencode((string)($smtp['password'] ?? ''));
            $host = $smtp['host'] ?? 'localhost';
            $port = (int)($smtp['port'] ?? 587);

            $dsn = ($user !== '' && $pass !== '')
                ? "smtp://{$user}:{$pass}@{$host}:{$port}"
                : "smtp://{$host}:{$port}";
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
    // Repositories (Doctrine DBAL, kein ORM)
    // -------------------------------------------------------------------------
    UserRepository::class               => DI\autowire(),
    ApiKeyRepository::class             => DI\autowire(),
    DomainRepository::class             => DI\autowire(),
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

    AuthMiddleware::class => DI\autowire(),

    // -------------------------------------------------------------------------
    // PSR-15-Handler
    // -------------------------------------------------------------------------
    HomeHandler::class => DI\autowire(),

    AuthHandler::class => DI\factory(function (ContainerInterface $c): AuthHandler {
        return new AuthHandler(
            $c->get(ThemeManager::class),
            $c->get(SessionContext::class),
            $c->get(UserRepository::class),
            $c->get(WebAuthnCredentialRepository::class),
            $c->get(TotpService::class),
            $c->get(PasswordHasher::class),
            $c->get(UserKeyManager::class),
            $c->get('config'),
        );
    }),

    DashboardHandler::class   => DI\autowire(),
    DomainHandler::class      => DI\autowire(),
    RecordHandler::class      => DI\autowire(),
    KeyHandler::class         => DI\autowire(),
    AdminHandler::class       => DI\autowire(),
    ProfileHandler::class     => DI\autowire(),
    WebAuthnApiHandler::class => DI\autowire(),
    TotpApiHandler::class     => DI\autowire(),

    ThemeManager::class => DI\factory(function (ContainerInterface $c): ThemeManager {
        /** @var array<string, mixed> $cfg */
        $cfg       = $c->get('config');
        $themeName = (string)($cfg['app']['theme'] ?? 'default');
        $projectRoot = dirname(__DIR__);

        return new ThemeManager(['theme' => ['name' => $themeName]], $projectRoot);
    }),
]);

return $builder->build();
