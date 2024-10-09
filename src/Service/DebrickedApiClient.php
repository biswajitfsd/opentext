<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DebrickedApiClient
{
    private $httpClient;
    private $username;
    private $password;
    private $repositoryName;
    private $baseUrl = 'https://debricked.com/api';
    private $token;

    public function __construct(
        HttpClientInterface $httpClient,
        string              $username,
        string              $password,
        string              $repositoryName
    )
    {
        $this->httpClient = $httpClient;
        $this->username = $username;
        $this->password = $password;
        $this->repositoryName = $repositoryName;
    }

    private function authenticate(): void
    {
        $response = $this->httpClient->request('POST', $this->baseUrl . '/login_check', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                '_username' => $this->username,
                '_password' => $this->password,
            ],
        ]);

        $data = $response->toArray();
        if (!isset($data['token'])) {
            throw new \RuntimeException('Failed to authenticate with Debricked API');
        }
        $this->token = $data['token'];
    }

    public function uploadFile(string $filePath): string
    {
        if (!$this->token) {
            $this->authenticate();
        }

        $file = new UploadedFile($filePath, basename($filePath));

        $response = $this->httpClient->request('POST', $this->baseUrl . '/1.0/open/uploads/dependencies/files', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => '*/*',
                'Content-Type' => 'multipart/form-data',
            ],
            'body' => [
                'commitName' => 'Initial commit',
                'ciUploadId' => '',
                'repositoryUrl' => 'https://github.com/' . $this->repositoryName,
                'fileData' => fopen($filePath, 'r'),
                'fileRelativePath' => $file->getClientOriginalName(),
                'branchName' => 'main',
                'defaultBranchName' => 'main',
                'releaseName' => '',
                'repositoryName' => $this->repositoryName,
                'productName' => '',
            ],
        ]);

        $data = $response->toArray();
        if (!isset($data['uploadId'])) {
            throw new \RuntimeException('Failed to get uploadId from Debricked API');
        }
        return $data['uploadId'];
    }

    public function startScan(string $uploadId): void
    {
        if (!$this->token) {
            $this->authenticate();
        }

        $response = $this->httpClient->request('POST', $this->baseUrl . "/1.0/open/finishes/dependencies/files/uploads/{$uploadId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to start scan for upload ' . $uploadId);
        }
    }

    public function isScanComplete(string $uploadId): bool
    {
        if (!$this->token) {
            $this->authenticate();
        }

        $response = $this->httpClient->request('GET', $this->baseUrl . "/1.0/open/ci/upload/status/{$uploadId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
        ]);

        $data = $response->toArray();
        return isset($data['status']) && $data['status'] === 'complete';
    }

    public function getScanResults(string $uploadId): array
    {
        if (!$this->token) {
            $this->authenticate();
        }

        $response = $this->httpClient->request('GET', $this->baseUrl . "/1.0/open/vulns/dependencies/files/uploads/{$uploadId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
        ]);

        $data = $response->toArray();
        if (!isset($data['vulnerabilities'])) {
            throw new \RuntimeException('Failed to get vulnerabilities from Debricked API');
        }
        return [
            'vulnerabilityCount' => count($data['vulnerabilities']),
            'vulnerabilities' => $data['vulnerabilities'],
        ];
    }

    public function getVulnerabilityDetails(string $vulnerabilityId): array
    {
        if (!$this->token) {
            $this->authenticate();
        }

        $response = $this->httpClient->request('GET', $this->baseUrl . "/1.0/open/vulns/{$vulnerabilityId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
        ]);

        $data = $response->toArray();
        if (!isset($data['cve'])) {
            throw new \RuntimeException('Failed to get vulnerability details from Debricked API');
        }
        return $data;
    }
}