<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Root-Handler: Leitet angemeldete User auf /dashboard, andere auf /auth/login.
 *
 * @todo Phase 2: Session-Prüfung und Weiterleitung implementieren.
 */
class HomeHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new RedirectResponse('/auth/login');
    }
}
