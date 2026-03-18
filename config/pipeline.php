<?php

declare(strict_types=1);

/**
 * Mezzio Middleware-Pipeline.
 *
 * Dieses Callable wird von public/index.php nach dem Container-Bootstrap aufgerufen.
 * Die Reihenfolge der pipe()-Aufrufe ist die tatsächliche Ausführungsreihenfolge.
 *
 * Middleware-Schichten (von außen nach innen):
 *   1. SecurityHeadersMiddleware  — HTTP-Sicherheits-Header (CSP, HSTS, etc.)
 *   2. SessionMiddleware          — Session-Start mit sicheren Cookie-Flags
 *   3. RouteMiddleware            — Route im Request-Attribut hinterlegen
 *   4. AuthMiddleware             — Session-basierter Auth-Guard (nur auth. Routen)
 *   5. DispatchMiddleware         — Handler aufrufen
 *   6. NotFoundHandler            — 404-Antwort
 *
 * Middleware in Kommentaren sind Platzhalter — zu implementieren in Phase 3.
 */

use Mezzio\Application;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Psr\Container\ContainerInterface;

return static function (
    Application       $app,
    MiddlewareFactory $factory,
    ContainerInterface $container
): void {
    // --- Sicherheits-Header (für alle Requests, vor dem Routing) ---
    $app->pipe(\App\Middleware\SecurityHeadersMiddleware::class);

    // --- Session-Initialisierung mit config/security.toml-Flags ---
    $app->pipe(\App\Middleware\SessionMiddleware::class);

    // --- Routing: Route aus dem Request ableiten ---
    $app->pipe(RouteMiddleware::class);

    // --- Routen-spezifische Middleware (Auth, CSRF, Rate-Limit) ---
    // Diese können alternativ direkt in config/routes.php pro Route eingebunden werden:
    //   $app->route('/dashboard', [\App\Middleware\AuthMiddleware::class, DashboardHandler::class], ...);
    //
    // Oder als globale Middleware mit Ausnahmen (Login-Route ist public):
    // $app->pipe(\App\Middleware\AuthMiddleware::class);

    // --- Dispatch: Den zur Route gehörenden Handler ausführen ---
    $app->pipe(DispatchMiddleware::class);

    // --- 404-Fallback ---
    $app->pipe(NotFoundHandler::class);
};
