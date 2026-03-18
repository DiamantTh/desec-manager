<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Authentifizierungs-Handler: Login, Logout, Registrierung, WebAuthn.
 *
 * @todo Phase 2: AuthController-Logik hierher migrieren.
 */
class AuthHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse('<h1>Auth – TODO Phase 2</h1>', 501);
    }
}
