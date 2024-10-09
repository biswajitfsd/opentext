<?php

namespace App\Controller;

use App\Entity\Upload;
use App\Message\ProcessUploadMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class UploadController extends AbstractController
{
    #[Route('/api/upload', name: 'api_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em, MessageBusInterface $messageBus): Response
    {
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $newFilename = $originalFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

        try {
            $uploadedFile->move(
                $this->getParameter('uploads_directory'),
                $newFilename
            );
        } catch (FileException $e) {
            return $this->json(['error' => 'Failed to upload file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $upload = new Upload();
        $upload->setFileName($newFilename);
        $upload->setStatus('pending');

        $em->persist($upload);
        $em->flush();

        $messageBus->dispatch(new ProcessUploadMessage($upload->getId()));

        return $this->json([
            'message' => 'File uploaded successfully',
            'filename' => $newFilename
        ], Response::HTTP_CREATED);
    }
}