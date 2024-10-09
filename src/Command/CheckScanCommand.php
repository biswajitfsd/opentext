<?php

namespace App\Command;

use App\Repository\UploadRepository;
use App\Service\DebrickedApiClient;
use App\Service\RuleEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:check-scan-status',
    description: 'Checks the status of ongoing scans and sends notifications if completed.',
)]
class CheckScanCommand extends Command
{
    private $uploadRepository;
    private $debrickedApiClient;
    private $ruleEngine;
    private $entityManager;
    private $logger;

    public function __construct(
        UploadRepository $uploadRepository,
        DebrickedApiClient $debrickedApiClient,
        RuleEngine $ruleEngine,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->uploadRepository = $uploadRepository;
        $this->debrickedApiClient = $debrickedApiClient;
        $this->ruleEngine = $ruleEngine;
        $this->entityManager = $entityManager;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Starting CheckScanStatusCommand');

        try {
            $uploads = $this->uploadRepository->findBy(['status' => 'scanning']);

            foreach ($uploads as $upload) {
                $this->logger->info('Checking upload: ' . $upload->getId());

                try {
                    if ($this->debrickedApiClient->isScanComplete($upload->getDebrickedUploadId())) {
                        $scanResults = $this->debrickedApiClient->getScanResults($upload->getRepositoryId(), $upload->getCommitId());

                        $upload->setStatus('completed');
                        $upload->setVulnerabilityCount($scanResults['vulnerabilityCount'] ?? 0);
                        $this->entityManager->flush();

                        $this->ruleEngine->evaluate($upload);
                        $this->logger->info('Scan completed for upload: ' . $upload->getId());
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error processing upload ' . $upload->getId() . ': ' . $e->getMessage());
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