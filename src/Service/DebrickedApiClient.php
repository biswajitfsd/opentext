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

        // Ensure the file exists
        if (!file_exists($filePath)) {
            throw new \RuntimeException('File does not exist: ' . $filePath);
        }

        $curl = curl_init();

        // Create a CURLFile instance
        $fileData = new \CURLFile($filePath);

        // Debug the CURLFile object
        // dd($fileData);

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
                'fileData' => $fileData, // Use the variable here
                'fileRelativePath' => '',
                'branchName' => '',
                'defaultBranchName' => '',
                'releaseName' => '',
                'repositoryName' => $this->repositoryName, // Use the instance variable
                'productName' => '',
            ],
            CURLOPT_HTTPHEADER => [
                'accept: */*',
                'Authorization: Bearer ' . $this->token,
                // Add any other headers you need
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