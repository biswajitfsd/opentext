<?php

namespace App\Service;

use App\Entity\Upload;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

class RuleEngine
{
    private $notifier;
    private $rules = [];

    public function __construct(NotifierInterface $notifier)
    {
        $this->notifier = $notifier;
        $this->setupRules();
    }

    private function setupRules(): void
    {
        $this->addRule(
            function (Upload $upload) {
                return $upload->getVulnerabilityCount() > 10;
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
        $notification = (new Notification('High Vulnerability Alert', ['email', 'slack']))
            ->content('Upload ' . $upload->getId() . ' has ' . $upload->getVulnerabilityCount() . ' vulnerabilities.')
            ->importance(Notification::IMPORTANCE_HIGH);

        $recipient = new Recipient('admin@example.com');

        $this->notifier->send($notification, $recipient);
    }

    private function notifyScanInProgress(Upload $upload): void
    {
        $notification = (new Notification('Scan In Progress', ['slack']))
            ->content('Scan for upload ' . $upload->getId() . ' is currently in progress.')
            ->importance(Notification::IMPORTANCE_MEDIUM);

        $recipient = new Recipient('admin@example.com');

        $this->notifier->send($notification, $recipient);
    }

    private function notifyScanFailed(Upload $upload): void
    {
        $notification = (new Notification('Scan Failed', ['email', 'slack']))
            ->content('Scan for upload ' . $upload->getId() . ' has failed.')
            ->importance(Notification::IMPORTANCE_HIGH);

        $recipient = new Recipient('admin@example.com');

        $this->notifier->send($notification, $recipient);
    }
}