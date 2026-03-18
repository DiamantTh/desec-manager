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
    $app->route('/auth/login',  [\App\Handler\AuthHandler::class], ['GET', 'POST'], 'auth.login');
    $app->route('/auth/logout', [\App\Handler\AuthHandler::class], ['POST'],        'auth.logout');

    // MFA-Zwischenschritte (kein user_id in Session, aber mfa_pending erforderlich)
    $app->route('/auth/mfa/totp',    [\App\Handler\AuthHandler::class], ['GET', 'POST'], 'auth.mfa.totp');
    $app->get('/auth/mfa/webauthn',  \App\Handler\AuthHandler::class,                   'auth.mfa.webauthn');

    // Redirect von / auf /dashboard oder /auth/login
    $app->get('/', \App\Handler\HomeHandler::class, 'home');

    // -------------------------------------------------------------------------
    // Dashboard (auth-geschützt)
    // -------------------------------------------------------------------------
    $app->get('/dashboard',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\DashboardHandler::class],
        'dashboard');

    // -------------------------------------------------------------------------
    // DNS-Domains (auth-geschützt)
    // -------------------------------------------------------------------------
    $app->get('/domains',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\DomainHandler::class],
        'domains.index');
    $app->route('/domains/add',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\DomainHandler::class],
        ['GET', 'POST'], 'domains.add');
    $app->route('/domains/{domain}/delete',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\DomainHandler::class],
        ['POST'], 'domains.delete');
    $app->route('/domains/{domain}/sync',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\DomainHandler::class],
        ['POST'], 'domains.sync');

    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    // DNS-Records (auth-geschützt)
    // -------------------------------------------------------------------------
    $app->get('/domains/{domain}/records',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\RecordHandler::class],
        'records.index');
    $app->route('/domains/{domain}/records/add',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\RecordHandler::class],
        ['GET', 'POST'], 'records.add');
    $app->route('/domains/{domain}/records/{type}/{name}',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\RecordHandler::class],
        ['GET', 'POST', 'DELETE'], 'records.edit');

    // -------------------------------------------------------------------------
    // API-Keys (auth-geschützt)
    // -------------------------------------------------------------------------
    $app->get('/keys',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\KeyHandler::class],
        'keys.index');
    $app->route('/keys/create',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\KeyHandler::class],
        ['GET', 'POST'], 'keys.create');
    $app->route('/keys/{id}/revoke',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\KeyHandler::class],
        ['POST'], 'keys.revoke');

    // -------------------------------------------------------------------------
    // Profil (auth-geschützt)
    // -------------------------------------------------------------------------
    $app->route('/profile',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\ProfileHandler::class],
        ['GET', 'POST'], 'profile');

    // -------------------------------------------------------------------------
    // Administration (auth-geschützt + Admin-Check im Handler)
    // -------------------------------------------------------------------------
    $app->get('/admin',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\AdminHandler::class],
        'admin.index');
    $app->route('/admin/users/{id}',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\AdminHandler::class],
        ['GET', 'POST', 'DELETE'], 'admin.user');
    $app->route('/admin/users',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\AdminHandler::class],
        ['POST'], 'admin.users.create');

    // -------------------------------------------------------------------------
    // WebAuthn JSON-API
    // -------------------------------------------------------------------------
    $app->get('/webauthn/register-options',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\WebAuthnApiHandler::class],
        'api.webauthn.register-options');
    $app->post('/webauthn/verify-registration',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\WebAuthnApiHandler::class],
        'api.webauthn.verify-registration');

    // Auth-Optionen + Verify sind während MFA-Flow ohne user_id erreichbar
    $app->get('/webauthn/auth-options',          \App\Handler\WebAuthnApiHandler::class, 'api.webauthn.auth-options');
    $app->post('/webauthn/verify-authentication', \App\Handler\WebAuthnApiHandler::class, 'api.webauthn.verify-authentication');

    $app->post('/webauthn/rename',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\WebAuthnApiHandler::class],
        'api.webauthn.rename');
    $app->post('/webauthn/delete',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\WebAuthnApiHandler::class],
        'api.webauthn.delete');

    // -------------------------------------------------------------------------
    // TOTP JSON-API (auth-geschützt)
    // -------------------------------------------------------------------------
    $app->get('/totp/setup',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\TotpApiHandler::class],
        'api.totp.setup');
    $app->post('/totp/enable',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\TotpApiHandler::class],
        'api.totp.enable');
    $app->post('/totp/disable',
        [\App\Middleware\AuthMiddleware::class, \App\Handler\TotpApiHandler::class],
        'api.totp.disable');
};
