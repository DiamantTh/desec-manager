<?php
namespace App\DeSEC;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class DeSECClient
{
    private const API_BASE_URL = 'https://desec.io/api/v1/domains/';
    
    private Client $client;
    
    /** @var array<string, string> */
    private array $headers;

    public function __construct(string $token)
    {
        if (empty($token)) {
            throw new DeSECException('API token cannot be empty');
        }

        $this->client = new Client([
            'base_uri' => self::API_BASE_URL,
            'timeout'  => 30,
            'http_errors' => true
        ]);
        
        $this->headers = [
            'Authorization' => 'Token ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws DeSECException
     */
    public function listDomains(): array
    {
        return $this->fetchAllPages('');
    }

    /**
     * @return array<string, mixed>
     * @throws DeSECException
     */
    public function createDomain(string $domainName): array
    {
        if (empty($domainName)) {
            throw new DeSECException('Domain name cannot be empty');
        }

        return $this->request('POST', '', ['name' => $domainName]);
    }

    /**
     * @return array<string, mixed>
     * @throws DeSECException
     */
    public function getDomain(string $domainName): array
    {
        return $this->request('GET', $this->getDomainPath($domainName));
    }

    /**
     * @throws DeSECException
     */
    public function deleteDomain(string $domainName): bool
    {
        $response = $this->request('DELETE', $this->getDomainPath($domainName), null, false);
        return $response->getStatusCode() === 204;
    }

    /**
     * @param list<string> $records
     * @return array<string, mixed>
     * @throws DeSECException
     */
    public function createRRSet(
        string $domainName,
        string $subname,
        string $type,
        array $records,
        int $ttl = 3600
    ): array {
        $data = [
            'subname' => $subname,
            'type' => strtoupper($type),
            'records' => $records,
            'ttl' => $ttl
        ];

        return $this->request('POST', $this->getDomainPath($domainName, 'rrsets/'), $data);
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws DeSECException
     */
    public function getRRSets(string $domainName): array
    {
        return $this->fetchAllPages($this->getDomainPath($domainName, 'rrsets/'));
    }

    /**
     * @return array<string, mixed>
     * @throws DeSECException
     */
    public function getRRSet(string $domainName, string $subname, string $type): array
    {
        $urlSubname = ($subname === '') ? '@' : $subname;
        return $this->request(
            'GET',
            $this->getDomainPath($domainName, sprintf('rrsets/%s/%s/', $urlSubname, strtoupper($type)))
        );
    }

    /**
     * @param list<string> $records
     * @return array<string, mixed>
     * @throws DeSECException
     */
    public function modifyRRSet(
        string $domainName,
        string $subname,
        string $type,
        array $records,
        int $ttl = 3600
    ): array {
        $data = [
            'records' => $records,
            'ttl' => $ttl
        ];

        $urlSubname = ($subname === '') ? '@' : $subname;
        return $this->request(
            'PATCH',
            $this->getDomainPath($domainName, sprintf('rrsets/%s/%s/', $urlSubname, strtoupper($type))),
            $data
        );
    }

    /**
     * @throws DeSECException
     */
    public function deleteRRSet(string $domainName, string $subname, string $type): bool
    {
        $urlSubname = ($subname === '') ? '@' : $subname;
        $response = $this->request(
            'DELETE',
            $this->getDomainPath($domainName, sprintf('rrsets/%s/%s/', $urlSubname, strtoupper($type))),
            null,
            false
        );
        return $response->getStatusCode() === 204;
    }

    /**
     * @param array<int, array<string, mixed>> $rrsets
     * @return array<string, mixed>
     * @throws DeSECException
     */
    public function bulkUpdateRRSets(string $domainName, array $rrsets): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->request('PUT', $this->getDomainPath($domainName, 'rrsets/'), $rrsets);
        return $result;
    }

    /**
     * @throws DeSECException
     */
    public function exportZonefile(string $domainName): string
    {
        return $this->request('GET', $this->getDomainPath($domainName, 'zonefile/'), null, false)
            ->getBody()
            ->getContents();
    }

    private function getDomainPath(string $domain, string $suffix = ''): string
    {
        return $domain . '/' . $suffix;
    }

    /**
     * Fetches all pages of a paginated GET endpoint and merges the results.
     *
     * @return array<int, array<string, mixed>>
     * @throws DeSECException
     */
    private function fetchAllPages(string $endpoint): array
    {
        $allResults = [];
        $cursor = null;
        $started = false;

        do {
            $options = [RequestOptions::HEADERS => $this->headers];

            if ($cursor !== null) {
                $options[RequestOptions::QUERY] = ['cursor' => $cursor];
            }

            try {
                $response = $this->client->request('GET', $endpoint, $options);
            } catch (GuzzleException $e) {
                throw new DeSECException(
                    'API request failed: ' . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }

            /** @var mixed $body */
            $body = json_decode($response->getBody()->getContents(), true);
            if (is_array($body)) {
                /** @var array<int, array<string, mixed>> $body */
                $allResults = array_merge($allResults, $body);
            }

            $cursor = $this->extractNextCursor($response->getHeader('Link'));
            $started = true;

        } while ($cursor !== null);

        return $allResults;
    }

    /**
     * Extracts the cursor value for the "next" relation from Link headers.
     *
     * @param string[] $linkHeaders
     */
    private function extractNextCursor(array $linkHeaders): ?string
    {
        $pattern = '/<[^>]+[?&]cursor=([^>&\s]*)>[^;]*;\s*rel="next"/';

        $header = array_find(
            $linkHeaders,
            fn(string $h): bool => (bool) preg_match($pattern, $h),
        );

        if ($header === null) {
            return null;
        }

        preg_match($pattern, $header, $matches);
        return urldecode($matches[1]);
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>>|null $data
     * @return mixed
     * @throws DeSECException
     */
    private function request(string $method, string $endpoint, ?array $data = null, bool $decodeJson = true)
    {
        try {
            $options = [RequestOptions::HEADERS => $this->headers];
            
            if ($data !== null) {
                $options[RequestOptions::JSON] = $data;
            }

            $response = $this->client->request($method, $endpoint, $options);
            
            return $decodeJson ? 
                json_decode($response->getBody()->getContents(), true) : 
                $response;
                
        } catch (GuzzleException $e) {
            throw new DeSECException(
                'API request failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }
}
