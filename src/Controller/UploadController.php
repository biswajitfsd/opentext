<?php

namespace App\Controller;

use App\Entity\Upload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mime\MimeTypes;

class UploadController extends AbstractController
{
    #[Route('/api/upload', name: 'api_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em): Response
    {
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugify($originalFilename);
        $mimeTypes = new MimeTypes();
        $extension = $mimeTypes->getExtensions($uploadedFile->getMimeType())[0] ?? $uploadedFile->getClientOriginalExtension();
        $newFilename = $safeFilename.'.'.$extension;

        $allowedExtensions = $this->getAllowedExtensions($newFilename);
        if (!in_array($extension, $allowedExtensions)) {
            return $this->json(['error' => 'File type not allowed'], Response::HTTP_BAD_REQUEST);
        }

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

        return $this->json([
            'message' => 'File uploaded successfully',
            'filename' => $newFilename
        ], Response::HTTP_CREATED);
    }

    public function slugify($text)
    {
        // Replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // Trim
        $text = trim($text, '-');

        // Remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // Lowercase
        $text = strtolower($text);

        // Return the slugified string
        return $text;
    }

    private function getAllowedExtensions(string $newFilename): array
    {
        $jsonData = file_get_contents(__DIR__ . '/../../config/allowed_extensions.json');
        $data = json_decode($jsonData, true);
        $allowedExtensions = [];

        foreach ($data as $item) {
            if (!empty($item['regex'])) {
                $regex = '/' . str_replace('/', '\\/', $item['regex']) . '/';
                if (preg_match($regex, $newFilename)) {
                    $allowedExtensions[] = pathinfo($newFilename, PATHINFO_EXTENSION);
                }
            }
        }

        return $allowedExtensions;
    }
}