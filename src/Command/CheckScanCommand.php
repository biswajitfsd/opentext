<?php

namespace App\Command;

use App\Message\ProcessUploadMessage;
use App\Repository\UploadRepository;
use App\Service\DebrickedApiClient;
use App\Service\RuleEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;


#[AsCommand(
    name: 'app:check-scan-status',
    description: 'Checks the status of ongoing scans and sends notifications if completed.',
)]
class CheckScanCommand extends Command
{
    private UploadRepository $uploadRepository;
    private DebrickedApiClient $debrickedApiClient;
    private RuleEngine $ruleEngine;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private MessageBusInterface $messageBus;

    public function __construct(
        UploadRepository       $uploadRepository,
        DebrickedApiClient     $debrickedApiClient,
        RuleEngine             $ruleEngine,
        EntityManagerInterface $entityManager,
        LoggerInterface        $logger,
        MessageBusInterface    $messageBus
    )
    {
        $this->uploadRepository = $uploadRepository;
        $this->debrickedApiClient = $debrickedApiClient;
        $this->ruleEngine = $ruleEngine;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->messageBus = $messageBus;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Starting CheckScanStatusCommand');

        try {
            $uploads = $this->uploadRepository->findBy(['status' => ['pending', 'scanning']]);
            foreach ($uploads as $upload) {
                if ($upload->getStatus() === 'pending') {
                    $this->messageBus->dispatch(new ProcessUploadMessage($upload->getId()));
                } else {
                    $this->logger->info('Checking upload: ' . $upload->getId());

                    try {
                        $this->ruleEngine->evaluate($upload);
                        if ($this->debrickedApiClient->isScanComplete($upload->getDebrickedUploadId())) {
                            $scanResults = $this->debrickedApiClient->getScanResults($upload->getRepositoryId(), $upload->getCommitId());

                            $upload->setStatus('completed');
                            $upload->setVulnerabilityCount($scanResults['vulnerabilityCount'] ?? 0);
                            $this->entityManager->flush();

                            $this->ruleEngine->evaluate($upload);
                            $this->logger->info('Scan completed for upload: ' . $upload->getId());
                        }
                    } catch (\Exception $e) {
                        $upload->setStatus('failed');
                        $this->ruleEngine->evaluate($upload);
                        $this->logger->error('Error processing upload ' . $upload->getId() . ': ' . $e->getMessage());
                    }
                }

            }

            $this->logger->info('CheckScanStatusCommand completed successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('CheckScanStatusCommand failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}