<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Record-Handler: Anzeige und Bearbeitung von DNS-Records einer Domain.
 *
 * @todo Phase 2: RecordController-Logik hierher migrieren.
 */
class RecordHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse('<h1>Records – TODO Phase 2</h1>', 501);
    }
}
