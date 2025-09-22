<?php

namespace App\Controller;

use App\Repository\DomainRepository;
use App\Repository\ApiKeyRepository;
use App\Service\SystemHealthService;

class DashboardController extends AbstractPageController
{
    private DomainRepository $domains;
    private ApiKeyRepository $apiKeys;
    private SystemHealthService $systemHealth;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->domains = new DomainRepository();
        $this->apiKeys = new ApiKeyRepository();
        $this->systemHealth = new SystemHealthService();
    }

    public function render(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $domainList = $this->domains->findByUserId($userId);
        $keyList = $this->apiKeys->findByUserId($userId);

        $stats = [
            'domains' => count($domainList),
            'apiKeys' => count($keyList),
        ];

        $this->renderTemplate('dashboard/index', [
            'stats' => $stats,
            'domains' => array_slice($domainList, 0, 5),
            'apiKeys' => array_slice($keyList, 0, 5),
            'cacheStatus' => $this->systemHealth->getCacheStatus(),
        ]);
    }
}
