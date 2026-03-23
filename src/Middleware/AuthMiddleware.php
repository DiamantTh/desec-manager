<?php

declare(strict_types=1);

namespace App\Middleware;

use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * AuthMiddleware — checks whether an authenticated user exists in the session.
 *
 * Verwendung:
 *   Pro-Route: [\App\Middleware\AuthMiddleware::class, SomeHandler::class]
 *
 * Leitet bei fehlendem `$_SESSION['user_id']` auf /auth/login um.
 * The originally requested URL is NOT stored (deliberate design decision
 * gegen `redirect_to`-Parameter wegen CSRF-Risiken bei offener URL-Weitergabe).
 *
 * Admin-Check: wird per `\App\Middleware\AdminMiddleware` oder im Handler selbst erledigt.
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (empty($_SESSION['user_id'])) {
            return new RedirectResponse('/auth/login');
        }

        return $handler->handle($request);
    }
}
