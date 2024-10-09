<?php

namespace App\MessageHandler;

use App\Entity\Upload;
use App\Message\ProcessUploadMessage;
use App\Service\DebrickedApiClient;
use App\Service\RuleEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ProcessUploadMessageHandler implements MessageHandlerInterface
{
    private $debrickedApiClient;
    private $ruleEngine;
    private $entityManager;
    private $params;

    public function __construct(
        DebrickedApiClient $debrickedApiClient,
        RuleEngine $ruleEngine,
        EntityManagerInterface $entityManager,
        ParameterBagInterface $params
    ) {
        $this->debrickedApiClient = $debrickedApiClient;
        $this->ruleEngine = $ruleEngine;
        $this->entityManager = $entityManager;
        $this->params = $params;
    }

    public function __invoke(ProcessUploadMessage $message)
    {
        $upload = $this->entityManager->getRepository(Upload::class)->find($message->getUploadId());
        $filePath = $this->params->get('uploads_directory') . '/' . $upload->getFileName();

        $debrickedUploadId = $this->debrickedApiClient->uploadFile($filePath);
        $this->debrickedApiClient->startScan($debrickedUploadId);

        $upload->setStatus('scanning');
        $this->entityManager->flush();

        while (!$this->debrickedApiClient->isScanComplete($debrickedUploadId)) {
            sleep(10);
        }

        $scanResults = $this->debrickedApiClient->getScanResults($debrickedUploadId);

        $upload->setStatus('completed');
        $upload->setVulnerabilityCount($scanResults['vulnerabilityCount']);
        $this->entityManager->flush();

        $this->ruleEngine->evaluate($upload);
    }
}