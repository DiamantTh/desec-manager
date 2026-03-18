<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Key-Handler: API-Schlüssel auflisten, erstellen und widerrufen.
 *
 * @todo Phase 2: KeyController-Logik hierher migrieren.
 */
class KeyHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse('<h1>API-Keys – TODO Phase 2</h1>', 501);
    }
}
