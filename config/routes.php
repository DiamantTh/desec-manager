<?php

declare(strict_types=1);

/**
 * Mezzio Routen-Konfiguration.
 *
 * Handler sind PSR-15-RequestHandlerInterface-Implementierungen.
 * Sie ersetzen die bisherigen Controller-Klassen (keine array $config-Konstruktoren mehr).
 *
 * Namenskonvention: App\Handler\{Name}Handler
 * Pfad:             src/Handler/{Name}Handler.php
 *
 * Handler werden in Phase 2 der Migration angelegt (eine Klasse pro bisherigem Controller).
 * Bis dahin kann der $app->route()-Aufruf mit einem temporären Inline-Handler oder einem
 * Wrapper-Handler für die bestehenden Controller erfolgen.
 *
 * Auth-Middleware-Strategie:
 *   Öffentliche Routen (login, register) werden ohne Auth-Middleware registriert.
 *   Alle anderen Routen erhalten AuthMiddleware als ersten Middleware-Layer:
 *   $app->route('/dashboard', [\App\Middleware\AuthMiddleware::class, DashboardHandler::class], ...);
 */

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

return static function (
    Application       $app,
    MiddlewareFactory $factory,
    ContainerInterface $container
): void {

    // -------------------------------------------------------------------------
    // Authentifizierung (öffentlich — kein Auth-Guard)
    // -------------------------------------------------------------------------
    $app->route('/auth/login',    [\App\Handler\AuthHandler::class], ['GET', 'POST'], 'auth.login');
    $app->route('/auth/logout',   [\App\Handler\AuthHandler::class], ['POST'],        'auth.logout');
    $app->route('/auth/register', [\App\Handler\AuthHandler::class], ['GET', 'POST'], 'auth.register');
    $app->route('/auth/webauthn', [\App\Handler\AuthHandler::class], ['GET', 'POST'], 'auth.webauthn');

    // Redirect von / auf /dashboard oder /auth/login
    $app->get('/', \App\Handler\HomeHandler::class, 'home');

    // -------------------------------------------------------------------------
    // Dashboard (auth-geschützt)
    // Auth-Middleware als ersten Layer eintragen sobald implementiert:
    //   [\App\Middleware\AuthMiddleware::class, \App\Handler\DashboardHandler::class]
    // -------------------------------------------------------------------------
    $app->get('/dashboard', \App\Handler\DashboardHandler::class, 'dashboard');

    // -------------------------------------------------------------------------
    // DNS-Domains
    // -------------------------------------------------------------------------
    $app->get('/domains',             \App\Handler\DomainHandler::class, 'domains.index');
    $app->route('/domains/add',       [\App\Handler\DomainHandler::class], ['GET', 'POST'], 'domains.add');
    $app->route('/domains/{domain}/delete', [\App\Handler\DomainHandler::class], ['POST'], 'domains.delete');

    // -------------------------------------------------------------------------
    // DNS-Records
    // -------------------------------------------------------------------------
    $app->get('/domains/{domain}/records',
        \App\Handler\RecordHandler::class, 'records.index');
    $app->route('/domains/{domain}/records/add',
        [\App\Handler\RecordHandler::class], ['GET', 'POST'], 'records.add');
    $app->route('/domains/{domain}/records/{type}/{name}',
        [\App\Handler\RecordHandler::class], ['GET', 'POST', 'DELETE'], 'records.edit');

    // -------------------------------------------------------------------------
    // API-Keys
    // -------------------------------------------------------------------------
    $app->get('/keys',                   \App\Handler\KeyHandler::class, 'keys.index');
    $app->route('/keys/create',          [\App\Handler\KeyHandler::class], ['GET', 'POST'], 'keys.create');
    $app->route('/keys/{id}/revoke',     [\App\Handler\KeyHandler::class], ['POST'], 'keys.revoke');

    // -------------------------------------------------------------------------
    // Profil
    // -------------------------------------------------------------------------
    $app->route('/profile', [\App\Handler\ProfileHandler::class], ['GET', 'POST'], 'profile');

    // -------------------------------------------------------------------------
    // Administration (Admin-Role-Check via AuthMiddleware oder AdminMiddleware)
    // -------------------------------------------------------------------------
    $app->get('/admin',                       \App\Handler\AdminHandler::class, 'admin.index');
    $app->route('/admin/users/{id}',          [\App\Handler\AdminHandler::class], ['GET', 'POST', 'DELETE'], 'admin.user');
};
