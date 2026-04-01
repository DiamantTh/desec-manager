<?php

declare(strict_types=1);

/**
 * Mezzio Middleware-Pipeline.
 *
 * Dieses Callable wird von public/index.php nach dem Container-Bootstrap aufgerufen.
 * Die Reihenfolge der pipe()-Aufrufe ist die tatsächliche Ausführungsreihenfolge.
 *
 * Middleware-Schichten (von außen nach innen):
 *   1. SentryMiddleware             — Exception-Capture (optional, wenn DSN konfiguriert)
 *   2. SecurityHeadersMiddleware    — HTTP-Sicherheits-Header (CSP, HSTS, etc.)
 *   3. SessionMiddleware            — Cookie-Flags setzen (VOR session_start)
 *   4. Mezzio\Session\SessionMiddleware — Session starten (PhpSessionPersistence)
 *   5. CsrfMiddleware               — CSRF-Guard in Request-Attribute hinterlegen
 *   6. SessionContextMiddleware     — SessionContext + Translator-Locale initialisieren
 *   7. RouteMiddleware              — Route im Request-Attribut hinterlegen
 *   8. DispatchMiddleware           — Handler aufrufen
 *   9. NotFoundHandler              — 404-Antwort
 *
 * Auth-Guard (AuthMiddleware) wird pro Route in app/routes.php eingebunden.
 */

use Mezzio\Application;
use Mezzio\Csrf\CsrfMiddleware;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Mezzio\Session\SessionMiddleware as MezzioSessionMiddleware;
use Psr\Container\ContainerInterface;

return static function (
    Application        $app,
    MiddlewareFactory  $factory,
    ContainerInterface $container
): void {
    // --- Sentry: Exception-Capture (nur aktiv wenn sentry.dsn konfiguriert) ---
    $app->pipe(\App\Middleware\SentryMiddleware::class);

    // --- Sicherheits-Header (für alle Requests, vor dem Routing) ---
    $app->pipe(\App\Middleware\SecurityHeadersMiddleware::class);

    // --- Cookie-Flags setzen (MUSS vor mezzio-session laufen, damit session_start die Flags übernimmt) ---
    $app->pipe(\App\Middleware\SessionMiddleware::class);

    // --- mezzio/mezzio-session: Session starten + SessionInterface an Request heften ---
    $app->pipe(MezzioSessionMiddleware::class);

    // --- CSRF-Schutz (mezzio/mezzio-csrf) ---
    // Guard wird in den Request-Attributen hinterlegt; Formulare müssen Token einbinden.
    // JSON-Requests (WebAuthn, TOTP-API) sind nicht betroffen, da CsrfMiddleware
    // das Guard-Attribut nur setzt — die Validierung passiert im Handler/AbstractHandler.
    $app->pipe(CsrfMiddleware::class);

    // --- SessionContext + Translator-Locale initialisieren ---
    $app->pipe(\App\Middleware\SessionContextMiddleware::class);

    // --- Routing: Route aus dem Request ableiten ---
    $app->pipe(RouteMiddleware::class);

    // --- Dispatch: Den zur Route gehörenden Handler ausführen ---
    $app->pipe(DispatchMiddleware::class);

    // --- 404-Fallback ---
    $app->pipe(NotFoundHandler::class);
};
