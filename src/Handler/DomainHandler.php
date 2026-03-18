<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\ApiKeyRepository;
use App\Repository\DomainRepository;
use App\Service\DNSService;
use App\Service\ThemeManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DomainHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager $theme,
        private readonly DomainRepository $domains,
        private readonly ApiKeyRepository $apiKeys,
        private readonly DNSService $dns,
    ) {
        parent::__construct($theme);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $this->userId();

        if ($request->getMethod() === 'POST') {
            return $this->handlePost($request, $userId);
        }

        $flash = $this->consumeFlash();
        return $this->render('domains/index', [
            'domains'     => $this->domains->findByUserId($userId),
            'apiKeys'     => $this->apiKeys->findByUserId($userId),
            'message'     => $flash['message'] ?? null,
            'messageType' => $flash['type']    ?? 'is-success',
        ]);
    }

    private function handlePost(ServerRequestInterface $request, int $userId): ResponseInterface
    {
        $body   = $request->getParsedBody();
        $action = $this->bodyString($body, 'action');

        try {
            if ($action === 'add') {
                $domain   = strtolower($this->bodyString($body, 'domain'));
                $apiKeyId = $this->bodyInt($body, 'api_key_id');

                if ($domain === '' || $apiKeyId === 0) {
                    throw new \InvalidArgumentException('Bitte Domain und API-Key auswählen.');
                }
                if (!preg_match('/^[a-z0-9.-]+$/', $domain)) {
                    throw new \InvalidArgumentException('Ungültiger Domainname.');
                }

                $this->dns->createDomain($userId, $apiKeyId, $domain);
                $this->flash('is-success', 'Domain wurde hinzugefügt.');

            } elseif ($action === 'delete') {
                $domain   = $this->bodyString($body, 'domain');
                $apiKeyId = $this->bodyInt($body, 'api_key_id');

                if ($domain === '' || $apiKeyId === 0) {
                    throw new \InvalidArgumentException('Domain und API-Key werden benötigt.');
                }

                $this->dns->deleteDomain($userId, $apiKeyId, $domain);
                $this->flash('is-success', 'Domain wurde entfernt.');

            } elseif ($action === 'sync') {
                $apiKeyId = $this->bodyInt($body, 'api_key_id');
                if ($apiKeyId === 0) {
                    throw new \InvalidArgumentException('Bitte einen API-Key auswählen.');
                }
                $result  = $this->dns->syncDomains($userId, $apiKeyId);
                $added   = count($result['added']);
                $removed = count($result['removed']);
                $this->flash('is-success', "Sync abgeschlossen ({$added} hinzugefügt, {$removed} entfernt).");
            }
        } catch (\Throwable $e) {
            $this->flash('is-danger', $e->getMessage());
        }

        return $this->redirect('/domains');
    }
}
