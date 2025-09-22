<?php
namespace App\DeSEC;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class DeSECClient
{
    private const API_BASE_URL = 'https://desec.io/api/v1/domains/';
    
    private Client $client;
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
     * @throws DeSECException
     */
    public function listDomains(): array
    {
        return $this->request('GET', '');
    }

    /**
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
     * @throws DeSECException
     */
    public function getRRSets(string $domainName): array
    {
        return $this->request('GET', $this->getDomainPath($domainName, 'rrsets/'));
    }

    /**
     * @throws DeSECException
     */
    public function getRRSet(string $domainName, string $subname, string $type): array
    {
        return $this->request(
            'GET',
            $this->getDomainPath($domainName, sprintf('rrsets/%s/%s/', $subname, strtoupper($type)))
        );
    }

    /**
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

        return $this->request(
            'PATCH',
            $this->getDomainPath($domainName, sprintf('rrsets/%s/%s/', $subname, strtoupper($type))),
            $data
        );
    }

    /**
     * @throws DeSECException
     */
    public function deleteRRSet(string $domainName, string $subname, string $type): bool
    {
        $response = $this->request(
            'DELETE',
            $this->getDomainPath($domainName, sprintf('rrsets/%s/%s/', $subname, strtoupper($type))),
            null,
            false
        );
        return $response->getStatusCode() === 204;
    }

    /**
     * @throws DeSECException
     */
    public function bulkUpdateRRSets(string $domainName, array $rrsets): array
    {
        return $this->request('PUT', $this->getDomainPath($domainName, 'rrsets/'), $rrsets);
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

    private function getDomainPath(string $domain, string $suffix = '/'): string
    {
        return $domain . $suffix;
    }

    /**
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
