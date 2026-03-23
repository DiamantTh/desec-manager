<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\ApiKeyRepository;
use App\Repository\DomainRepository;
use App\Service\SystemHealthService;
use App\Service\ThemeManager;
use App\Session\SessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DashboardHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager $theme,
        SessionContext $sessionContext,
        private readonly DomainRepository $domains,
        private readonly ApiKeyRepository $apiKeys,
        private readonly SystemHealthService $systemHealth,
    ) {
        parent::__construct($theme, $sessionContext);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId    = $this->userId();
        $domainList = $this->domains->findByUserId($userId);
        $keyList    = $this->apiKeys->findByUserId($userId);

        return $this->render('dashboard/index', [
            'stats'       => [
                'domains' => count($domainList),
                'apiKeys' => count($keyList),
            ],
            'domains'     => array_slice($domainList, 0, 5),
            'apiKeys'     => array_slice($keyList, 0, 5),
            'cacheStatus' => $this->systemHealth->getCacheStatus(),
        ]);
    }
}
