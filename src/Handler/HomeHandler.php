<?php

declare(strict_types=1);

namespace App\Handler;

use App\Session\SessionContext;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * HomeHandler — leitet / auf /dashboard oder /auth/login weiter.
 */
class HomeHandler implements RequestHandlerInterface
{
    public function __construct(private readonly SessionContext $sessionContext)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->sessionContext->has('user_id')) {
            return new RedirectResponse('/dashboard');
        }
        return new RedirectResponse('/auth/login');
    }
}
