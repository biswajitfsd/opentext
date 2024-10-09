<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DebrickedApiClient
{
    private HttpClientInterface $httpClient;
    private string $username;
    private string $password;
    private string $repositoryName;
    private string $baseUrl = 'https://debricked.com/api';
    private string $token;

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
        $this->authenticate();
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

        if (!file_exists($filePath)) {
            throw new \RuntimeException('File does not exist: ' . $filePath);
        }

        $curl = curl_init();

        $fileData = new \CURLFile($filePath);

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->baseUrl . '/1.0/open/uploads/dependencies/files',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                'commitName' => 'Initial commit',
                'repositoryUrl' => '',
                'fileData' => $fileData,
                'fileRelativePath' => '',
                'branchName' => '',
                'defaultBranchName' => '',
                'releaseName' => '',
                'repositoryName' => $this->repositoryName,
                'productName' => '',
            ],
            CURLOPT_HTTPHEADER => [
                'accept: */*',
                'Authorization: Bearer ' . $this->token,
            ],
            CURLOPT_VERBOSE => true, // Enable verbose output
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException('cURL error: ' . $error_msg);
        }

        curl_close($curl);

        $data = json_decode($response, true);
        
        if (!isset($data['ciUploadId'])) {
            throw new \RuntimeException('Failed to get uploadId from Debricked API');
        }

        return $data['ciUploadId'];
    }

    public function startScan(string $uploadId): array
    {
        if (!$this->token) {
            $this->authenticate();
        }

        $curl = curl_init();

        $postData = [
            'debrickedConfig' => json_encode([
                'overrides' => [
                    'pURL' => 'string',
                    'version' => 'string',
                    'fileRegexes' => ['string'],
                ],
            ]),
            'commitName' => 'Initial commit',
            'ciUploadId' => $uploadId,
            'author' => 'null',
            'returnCommitData' => 'false',
            'repositoryName' => $this->repositoryName,
            'debrickedIntegration' => 'null',
            'repositoryZip' => '',
            'integrationName' => 'null',
            'versionHint' => 'false',
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->baseUrl . '/1.0/open/finishes/dependencies/files/uploads',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->token,
            ],
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException('cURL error: ' . $error_msg);
        }

        curl_close($curl);

        $data = json_decode($response, true);

        if (!isset($data['repositoryId']) || !isset($data['commitId'])) {
            throw new \RuntimeException('Failed to start scan for upload ' . $uploadId);
        }

        return [$data['repositoryId'], $data['commitId']];
    }

    public function isScanComplete(string $uploadId): bool
    {
        if (!$this->token) {
            $this->authenticate();
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->baseUrl . "/1.0/open/ci/upload/status?ciUploadId={$uploadId}&extendedOutput=true",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'accept: */*',
                'Authorization: Bearer ' . $this->token,
            ],
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException('cURL error: ' . $error_msg);
        }

        curl_close($curl);

        $data = json_decode($response, true);

        if (!isset($data['progress'])) {
            throw new \RuntimeException('Failed to get scan status from Debricked API');
        }

        return $data['progress'] === 100;
    }

    public function getScanResults(string $repositoryId, string $commitId): array
    {
        if (!$this->token) {
            $this->authenticate();
        }

        $curl = curl_init();

        $url = $this->baseUrl . "/1.0/open/vulnerabilities/get-vulnerabilities?page=1&rowsPerPage=25&order=asc&repositoryId={$repositoryId}&commitId={$commitId}";

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'accept: */*',
                'Authorization: Bearer ' . $this->token,
            ],
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException('cURL error: ' . $error_msg);
        }

        curl_close($curl);

        $data = json_decode($response, true);

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