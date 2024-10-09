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
        MailerInterface $mailer,
        HttpClientInterface $httpClient,
        string $slackDsn
    ) {
        $this->mailer = $mailer;
        $this->httpClient = $httpClient;
        $this->slackWebhookUrl = $slackDsn;
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

        $this->addRule(
            function (Upload $upload) {
                return $upload->getStatus() === 'completed';
            },
            function (Upload $upload) {
                $this->notifyScanCompleted($upload);
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
        $this->sendNotification('High Vulnerability Alert', $message);
    }

    private function notifyScanInProgress(Upload $upload): void
    {
        $message = 'Scan In Progress: Scan for upload ' . $upload->getId() . ' is currently in progress.';
        $this->sendNotification('Scan In Progress', $message);
    }

    private function notifyScanFailed(Upload $upload): void
    {
        $message = 'Scan Failed: Scan for upload ' . $upload->getId() . ' has failed.';
        $this->sendNotification('Scan Failed', $message);
    }

    private function notifyScanCompleted(Upload $upload): void
    {
        $message = 'Scan Completed: Scan for upload ' . $upload->getId() . ' has completed. Vulnerability count: ' . $upload->getVulnerabilityCount();
        $this->sendNotification('Scan Completed', $message);
    }

    private function sendNotification(string $subject, string $message): void
    {
        $this->sendEmailNotification($subject, $message);
        $this->sendSlackNotification($message);
    }

    private function sendEmailNotification(string $subject, string $message): void
    {
        $email = (new Email())
            ->from('noreply@example.com')
            ->to('biswajit1305@gmail.com')
            ->subject($subject)
            ->text($message);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Failed to send email notification: ' . $e->getMessage());
        }
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
            error_log('Failed to send Slack notification: ' . $e->getMessage());
        }
    }
}