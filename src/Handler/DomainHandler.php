<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Domain-Handler: Liste, Hinzufügen, Löschen von DNS-Domains.
 *
 * @todo Phase 2: DomainController-Logik hierher migrieren.
 */
class DomainHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse('<h1>Domains – TODO Phase 2</h1>', 501);
    }
}
