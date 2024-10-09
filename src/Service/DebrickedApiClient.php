<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DebrickedApiClient
{
    private $httpClient;
    private $apiToken;

    public function __construct(HttpClientInterface $httpClient, string $apiToken)
    {
        $this->httpClient = $httpClient;
        $this->apiToken = $apiToken;
    }

    public function uploadFile(string $filePath): string
    {
        $response = $this->httpClient->request('POST', 'https://debricked.com/api/1.0/open/uploads/dependencies/files', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
            ],
            'body' => [
                'file' => fopen($filePath, 'r'),
            ],
        ]);

        $data = $response->toArray();
        return $data['uploadId'];
    }

    public function startScan(string $uploadId): void
    {
        $this->httpClient->request('POST', "https://debricked.com/api/1.0/open/finishes/dependencies/files/uploads/{$uploadId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
            ],
        ]);
    }

    public function isScanComplete(string $uploadId): bool
    {
        $response = $this->httpClient->request('GET', "https://debricked.com/api/1.0/open/ci/upload/status/{$uploadId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
            ],
        ]);

        $data = $response->toArray();
        return $data['status'] === 'complete';
    }

    public function getScanResults(string $uploadId): array
    {
        $response = $this->httpClient->request('GET', "https://debricked.com/api/1.0/open/vulns/dependencies/files/uploads/{$uploadId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
            ],
        ]);

        return $response->toArray();
    }
}