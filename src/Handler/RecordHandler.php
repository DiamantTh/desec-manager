<?php

declare(strict_types=1);

namespace App\Handler;

use App\Repository\ApiKeyRepository;
use App\Repository\DomainRepository;
use App\Service\DNSService;
use App\Service\ThemeManager;
use App\Service\AuthorizationService;
use App\Session\SessionContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

class RecordHandler extends AbstractHandler implements RequestHandlerInterface
{
    public function __construct(
        ThemeManager $theme,
        SessionContext $sessionContext,
        AuthorizationService $authz,
        private readonly DomainRepository $domains,
        private readonly ApiKeyRepository $apiKeys,
        private readonly DNSService $dns,
    ) {
        parent::__construct($theme, $sessionContext, $authz);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId  = $this->userId();
        $domains = $this->domains->findByUserId($userId);
        $apiKeys = $this->apiKeys->findByUserId($userId);

        $queryParams     = $request->getQueryParams();
        $selectedDomain  = (string) ($queryParams['domain']  ?? ($domains[0]['domain_name'] ?? ''));
        $selectedKeyId   = isset($queryParams['api_key']) ? (int) $queryParams['api_key'] : ((int) ($apiKeys[0]['id'] ?? 0));

        if ($request->getMethod() === 'POST') {
            return $this->handlePost($request, $userId, $selectedDomain, $selectedKeyId);
        }

        $flash       = $this->consumeFlash();
        $message     = $flash['message'] ?? null;
        $messageType = $flash['type']    ?? 'is-success';

        $rrsets = [];
        if ($selectedDomain !== '' && $selectedKeyId !== 0) {
            try {
                $rrsets = $this->dns->listRRSets($userId, $selectedKeyId, $selectedDomain);
            } catch (\Throwable $e) {
                $message     = $e->getMessage();
                $messageType = 'is-danger';
            }
        }

        return $this->render('records/index', [
            'domains'        => $domains,
            'apiKeys'        => $apiKeys,
            'selectedDomain' => $selectedDomain,
            'selectedKeyId'  => $selectedKeyId,
            'rrsets'         => $rrsets,
            'csrfToken'      => $this->generateCsrfToken($request),
            'message'        => $message,
            'messageType'    => $messageType,
        ], $request);
    }

    private function handlePost(
        ServerRequestInterface $request,
        int $userId,
        string $selectedDomain,
        int $selectedKeyId
    ): ResponseInterface {
        if ($csrfError = $this->validateCsrf($request)) {
            return $csrfError;
        }

        $body     = $request->getParsedBody();
        $action   = $this->bodyString($body, 'action');
        $domain   = $this->bodyString($body, 'domain')   ?: $selectedDomain;
        $apiKeyId = $this->bodyInt($body, 'api_key_id') ?: $selectedKeyId;

        if ($domain === '' || $apiKeyId === 0) {
            $this->flash('is-danger', __('Please select a domain and API key.'));
            return $this->redirectToRecords($domain, $apiKeyId);
        }

        try {
            if ($action === 'create' || $action === 'update') {
                $this->handleUpsert($body, $userId, $apiKeyId, $domain, $action);
                $verb = $action === 'create' ? 'angelegt' : 'aktualisiert';
                $this->flash('is-success', "RRset wurde {$verb}.");
            } elseif ($action === 'delete') {
                $this->handleDelete($body, $userId, $apiKeyId, $domain);
                $this->flash('is-success', 'RRset wurde gelöscht.');
            }
        } catch (\Throwable $e) {
            $this->flash('is-danger', $e->getMessage());
        }

        return $this->redirectToRecords($domain, $apiKeyId);
    }

    /**
     * @param array<string, mixed>|object|null $body
     */
    private function handleUpsert(
        array|object|null $body,
        int $userId,
        int $apiKeyId,
        string $domain,
        string $action
    ): void {
        $type    = strtoupper($this->bodyString($body, 'type'));
        $subName = $this->bodyString($body, 'subname');
        if ($subName === '@') {
            $subName = '';
        }
        $ttl        = $this->bodyInt($body, 'ttl', 3600);
        $recordsRaw = $this->bodyString($body, 'records');

        $allowed = ['A', 'AAAA', 'ALIAS', 'CNAME', 'TXT', 'MX', 'NS', 'SRV', 'CAA', 'TLSA'];
        if (!in_array($type, $allowed, true)) {
            throw new RuntimeException(__('Record type not supported.'));
        }
        if ($ttl < 30 || $ttl > 86400) {
            throw new RuntimeException('TTL muss zwischen 30 und 86400 liegen.');
        }

        $records = $this->parseRecords($recordsRaw);
        if ($records === []) {
            throw new RuntimeException(__('At least one record must be specified.'));
        }

        if ($type === 'MX') {
            foreach ($records as $record) {
                if (!preg_match('/^\d+\s+\S+$/', $record)) {
                    throw new RuntimeException('MX-Records benötigen Priorität und Host (z. B. "10 mail.example.com").');
                }
            }
        }

        $this->dns->upsertRRSet($userId, $apiKeyId, $domain, $subName, $type, $records, $ttl);
    }

    /**
     * @param array<string, mixed>|object|null $body
     */
    private function handleDelete(array|object|null $body, int $userId, int $apiKeyId, string $domain): void
    {
        $type    = strtoupper($this->bodyString($body, 'type'));
        $subName = $this->bodyString($body, 'subname');
        if ($subName === '@') {
            $subName = '';
        }
        if ($type === '') {
            throw new RuntimeException(__('Record type missing.'));
        }

        $this->dns->deleteRRSet($userId, $apiKeyId, $domain, $subName, $type);
    }

    /**
     * @return list<string>
     */
    private function parseRecords(string $raw): array
    {
        $lines = preg_split('/\r?\n/', $raw);
        if ($lines === false) {
            return [];
        }
        $result = [];
        foreach ($lines as $line) {
            $value = trim($line);
            if ($value !== '') {
                $result[] = $value;
            }
        }
        return $result;
    }

    private function redirectToRecords(string $domain, int $apiKeyId): ResponseInterface
    {
        $query = http_build_query(array_filter([
            'domain'  => $domain   !== '' ? $domain  : null,
            'api_key' => $apiKeyId !== 0  ? $apiKeyId : null,
        ]));
        return $this->redirect('/domains/' . rawurlencode($domain) . '/records' . ($query !== '' ? '?' . $query : ''));
    }
}
