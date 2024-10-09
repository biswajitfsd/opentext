<?php

namespace App\MessageHandler;

use App\Entity\Upload;
use App\Message\ProcessUploadMessage;
use App\Service\DebrickedApiClient;
use App\Service\RuleEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

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

        if (!$upload) {
            throw new UnrecoverableMessageHandlingException('Upload not found for ID: ' . $message->getUploadId());
        }

        $fileName = $upload->getFileName();

        if ($fileName === null) {
            throw new UnrecoverableMessageHandlingException('Filename is null for Upload ID: ' . $message->getUploadId());
        }

        $uploadsDirectory = $this->params->get('uploads_directory');

        if ($uploadsDirectory === null) {
            throw new UnrecoverableMessageHandlingException('Uploads directory parameter is not set');
        }

        $filePath = $uploadsDirectory . '/' . $fileName;

        if (!file_exists($filePath)) {
            throw new UnrecoverableMessageHandlingException('File not found: ' . $filePath);
        }

        try {
            $debrickedUploadId = $this->debrickedApiClient->uploadFile($filePath);
            $this->debrickedApiClient->startScan($debrickedUploadId);

            $upload->setStatus('scanning');
            $this->entityManager->flush();

            while (!$this->debrickedApiClient->isScanComplete($debrickedUploadId)) {
                sleep(10);
            }

            $scanResults = $this->debrickedApiClient->getScanResults($debrickedUploadId);

            $upload->setStatus('completed');
            $upload->setVulnerabilityCount($scanResults['vulnerabilityCount'] ?? 0);
            $this->entityManager->flush();

            $this->ruleEngine->evaluate($upload);
        } catch (\Exception $e) {
            $upload->setStatus('failed');
            $this->entityManager->flush();
            throw new UnrecoverableMessageHandlingException('Error processing upload: ' . $e->getMessage(), 0, $e);
        }
    }
}