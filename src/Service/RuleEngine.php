<?php

namespace App\Service;

use App\Entity\Upload;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RuleEngine
{
    private $mailer;
    private $httpClient;
    private $slackWebhookUrl;
    private $rules = [];

    public function __construct(
        MailerInterface     $mailer,
        HttpClientInterface $httpClient,
        string              $slackWebhookUrl
    )
    {
        $this->mailer = $mailer;
        $this->httpClient = $httpClient;
        $this->slackWebhookUrl = $slackWebhookUrl;
        $this->setupRules();
    }

    private function setupRules(): void
    {
        $this->addRule(
            function (Upload $upload) {
                return $upload->getVulnerabilityCount() > 1;
            },
            function (Upload $upload) {
                $this->notifyHighVulnerabilities($upload);
            }
        );

        $this->addRule(
            function (Upload $upload) {
                return $upload->getStatus() === 'scanning';
            },
            function (Upload $upload) {
                $this->notifyScanInProgress($upload);
            }
        );

        $this->addRule(
            function (Upload $upload) {
                return $upload->getStatus() === 'failed';
            },
            function (Upload $upload) {
                $this->notifyScanFailed($upload);
            }
        );
    }

    public function addRule(callable $condition, callable $action): void
    {
        $this->rules[] = ['condition' => $condition, 'action' => $action];
    }

    public function evaluate(Upload $upload): void
    {
        foreach ($this->rules as $rule) {
            if (call_user_func($rule['condition'], $upload)) {
                call_user_func($rule['action'], $upload);
            }
        }
    }

    private function notifyHighVulnerabilities(Upload $upload): void
    {
        $message = 'High Vulnerability Alert: Upload ' . $upload->getId() . ' has ' . $upload->getVulnerabilityCount() . ' vulnerabilities.';

        // Send Slack notification
        $this->sendSlackNotification($message);

        // Send email notification
        $email = (new Email())
            ->from('noreply@example.com')
            ->to('biswajit1305@gmail.com')
            ->subject('High Vulnerability Alert')
            ->text($message);

        $this->mailer->send($email);
    }

    private function notifyScanInProgress(Upload $upload): void
    {
        $message = 'Scan In Progress: Scan for upload ' . $upload->getId() . ' is currently in progress.';

        $this->sendSlackNotification($message);

        // Send email notification
        $email = (new Email())
            ->from('noreply@example.com')
            ->to('biswajit1305@gmail.com')
            ->subject('Scan In Progress')
            ->text($message);

        $this->mailer->send($email);
    }

    private function notifyScanFailed(Upload $upload): void
    {
        $message = 'Scan Failed: Scan for upload ' . $upload->getId() . ' has failed.';

        $this->sendSlackNotification($message);

        // Send email notification
        $email = (new Email())
            ->from('noreply@example.com')
            ->to('biswajit1305@gmail.com')
            ->subject('Scan Failed')
            ->text($message);

        $this->mailer->send($email);
    }

    private function sendSlackNotification(string $message): void
    {
        try {
            $response = $this->httpClient->request('POST', $this->slackWebhookUrl, [
                'json' => ['text' => $message]
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Slack API returned non-200 status code: ' . $response->getStatusCode());
            }
        } catch (\Exception $e) {
            // Log the error or handle it appropriately
            error_log('Failed to send Slack notification: ' . $e->getMessage());
        }
    }
}