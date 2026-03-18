<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Profile-Handler: Passwort- und Profilangaben des eingeloggten Users.
 *
 * @todo Phase 2: ProfileController-Logik hierher migrieren.
 */
class ProfileHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse('<h1>Profil – TODO Phase 2</h1>', 501);
    }
}
