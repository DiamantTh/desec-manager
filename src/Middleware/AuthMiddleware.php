<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Session\SessionContext;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * AuthMiddleware — prüft ob ein authentifizierter Benutzer in der Session vorhanden ist.
 *
 * Verwendung:
 *   Pro-Route: [\App\Middleware\AuthMiddleware::class, SomeHandler::class]
 *
 * Leitet bei fehlendem `user_id` in der Session auf /auth/login um.
 * The originally requested URL is NOT stored (deliberate design decision
 * gegen `redirect_to`-Parameter wegen CSRF-Risiken bei offener URL-Weitergabe).
 *
 * Admin-Check: wird per `\App\Middleware\AdminMiddleware` oder im Handler selbst erledigt.
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly SessionContext $sessionContext)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->sessionContext->has('user_id')) {
            return new RedirectResponse('/auth/login');
        }

        return $handler->handle($request);
    }
}
