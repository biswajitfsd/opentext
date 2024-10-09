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

    public function notifyHighVulnerabilities(Upload $upload): void
    {
        $notification = (new Notification('High Vulnerability Alert', ['email', 'slack']))
            ->content('Upload ' . $upload->getId() . ' has ' . $upload->getVulnerabilityCount() . ' vulnerabilities.')
            ->importance(Notification::IMPORTANCE_HIGH);

        $recipient = new Recipient('admin@example.com');

        $this->notifier->send($notification, $recipient);
    }
}