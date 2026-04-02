<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\ApiKeyRepository;
use App\Repository\DomainRepository;
use App\Service\AuthorizationService;
use App\Service\DNSService;
use App\Service\ThemeManager;
use App\Session\SessionContext;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * RecordsApiHandler — JSON-API für Svelte Records-App.
 *
 * GET  /api/domains/{domain}/records?key_id=…   → RRset-Liste
 */
class RecordsApiHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager         $theme,
        SessionContext        $sessionContext,
        AuthorizationService $authz,
        private readonly DomainRepository $domains,
        private readonly ApiKeyRepository $apiKeys,
        private readonly DNSService       $dns,
    ) {
        parent::__construct($theme, $sessionContext, $authz);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId  = $this->userId();
        $domain  = (string) ($request->getAttribute('domain') ?? '');
        $keyId   = (int) ($request->getQueryParams()['key_id'] ?? 0);

        if ($domain === '' || $keyId === 0) {
            return new JsonResponse(['error' => 'domain und key_id erforderlich.'], 400);
        }

        // Sicherstellen dass Domain + Key dem Benutzer gehören
        $userDomains = array_column($this->domains->findByUserId($userId), 'domain_name');
        if (!in_array($domain, $userDomains, true)) {
            return new JsonResponse(['error' => 'Domain nicht gefunden.'], 404);
        }

        $userKeyIds = array_column($this->apiKeys->findByUserId($userId), 'id');
        if (!in_array($keyId, $userKeyIds, true)) {
            return new JsonResponse(['error' => 'API-Key nicht gefunden.'], 404);
        }

        try {
            $rrsets = $this->dns->listRRSets($userId, $keyId, $domain);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        }

        return new JsonResponse($rrsets);
    }
}
