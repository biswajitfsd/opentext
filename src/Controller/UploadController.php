<?php

namespace App\Controller;

use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends AbstractController
{
    private FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    #[Route('/api/upload', name: 'api_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em): Response
    {
        $uploadedFiles = $request->files->get('files');

        if (!$uploadedFiles) {
            return $this->json(['error' => 'No files uploaded'], Response::HTTP_BAD_REQUEST);
        }

        $uploadResults = [];

        foreach ($uploadedFiles as $uploadedFile) {
            $uploadResults[] = $this->fileUploadService->processUploadedFile($uploadedFile, $em);
        }

        return $this->json([
            'message' => 'Files processed',
            'results' => $uploadResults
        ], Response::HTTP_CREATED);
    }
}