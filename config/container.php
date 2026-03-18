<?php

declare(strict_types=1);

/**
 * PHP-DI Container-Konfiguration für die Mezzio-Anwendung.
 *
 * Dieser Container dient als PSR-11-Implementierung für Mezzio.
 * PHP-DI ist direkt kompatibel — kein Slim-Bridge-Paket nötig.
 *
 * Verwendung in public/index.php:
 *   $container = require __DIR__ . '/../config/container.php';
 */

use App\Config\TomlLoader;
use App\Repository\ApiKeyRepository;
use App\Repository\DomainRepository;
use App\Repository\UserRepository;
use App\Security\EncryptionService;
use App\Security\PasswordHasher;
use App\Security\TotpService;
use App\Security\WebAuthnService;
use App\Service\DeSECProxyService;
use App\Service\DNSService;
use App\Service\ThemeManager;
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
use Mezzio\Template\TemplateRendererInterface;
use Mezzio\Twig\TwigEnvironmentFactory;
use Mezzio\Twig\TwigRenderer;
use Mezzio\Twig\TwigRendererFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Twig\Environment;

// --- Konfiguration aus TOML-Dateien laden ---
$tomlLoader = new TomlLoader(dirname(__DIR__) . '/config');
$appConfig  = $tomlLoader->load();

// Mezzio-spezifische Konfiguration einbetten (vom mezzio-twigrenderer erwartet)
$appConfig['templates'] = [
    'extension' => 'php',
    'paths'     => [
        'app'    => [dirname(__DIR__) . '/templates'],
        'error'  => [dirname(__DIR__) . '/templates/error'],
    ],
];

$appConfig['twig'] = [
    'cache_dir'          => dirname(__DIR__) . '/var/cache/twig',
    'auto_reload'        => (bool)($appConfig['app']['debug'] ?? false),
    'autoescape'         => 'html',
    'autoescape_service' => null,
    'globals'            => [],
    'extensions'         => [],
];

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
    // Template-Renderer (Twig via mezzio-twigrenderer)
    // -------------------------------------------------------------------------
    Environment::class => DI\factory(function (ContainerInterface $c): Environment {
        return (new TwigEnvironmentFactory())($c);
    }),

    TwigRenderer::class => DI\factory(function (ContainerInterface $c): TwigRenderer {
        return (new TwigRendererFactory())($c);
    }),

    TemplateRendererInterface::class => DI\get(TwigRenderer::class),

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
        return new WebAuthnService($cfg);
    }),

    TotpService::class => DI\factory(function (ContainerInterface $c): TotpService {
        /** @var array<string, mixed> $cfg */
        $cfg = $c->get('config');
        return new TotpService($cfg);
    }),

    // -------------------------------------------------------------------------
    // Repositories (Doctrine DBAL, kein ORM)
    // -------------------------------------------------------------------------
    UserRepository::class   => DI\autowire(),
    ApiKeyRepository::class => DI\autowire(),
    DomainRepository::class => DI\autowire(),

    // -------------------------------------------------------------------------
    // Anwendungsdienste
    // -------------------------------------------------------------------------
    DNSService::class       => DI\autowire(),
    DeSECProxyService::class => DI\autowire(),

    ThemeManager::class => DI\factory(function (ContainerInterface $c): ThemeManager {
        /** @var array<string, mixed> $cfg */
        $cfg       = $c->get('config');
        $themeName = (string)($cfg['app']['theme'] ?? 'default');
        $projectRoot = dirname(__DIR__);

        return new ThemeManager(['theme' => ['name' => $themeName]], $projectRoot);
    }),
]);

return $builder->build();
