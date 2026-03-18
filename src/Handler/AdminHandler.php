<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Admin-Handler: Benutzerverwaltung für Administratoren.
 *
 * @todo Phase 2: AdminController-Logik hierher migrieren.
 */
class AdminHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse('<h1>Admin – TODO Phase 2</h1>', 501);
    }
}
