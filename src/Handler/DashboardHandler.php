<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Dashboard-Handler: Übersicht nach dem Login.
 *
 * @todo Phase 2: DashboardController-Logik hierher migrieren.
 */
class DashboardHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse('<h1>Dashboard – TODO Phase 2</h1>', 501);
    }
}
