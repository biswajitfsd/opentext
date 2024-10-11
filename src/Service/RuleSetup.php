<?php

namespace App\Service;

use App\Entity\Upload;

class RuleSetup
{
    private RuleEngine $ruleEngine;

    public function __construct(RuleEngine $ruleEngine)
    {
        $this->ruleEngine = $ruleEngine;
    }

    public function setupRules(): void
    {
        $this->ruleEngine->addRule(
            function (Upload $upload) {
                return $upload->getVulnerabilityCount() > 1;
            },
            [$this->ruleEngine, 'notifyHighVulnerabilities']
        );
    }
}