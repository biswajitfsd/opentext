<?php

namespace App\Service;

use App\Entity\Upload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

class FileUploadService
{
    private string $uploadDirectory;
    private EntityManagerInterface $entityManager;

    public function __construct(string $uploadDirectory, EntityManagerInterface $entityManager)
    {
        $this->uploadDirectory = $uploadDirectory;
        $this->entityManager = $entityManager;
    }

    public function processUploadedFile(UploadedFile $uploadedFile): array
    {
        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugify($originalFilename);
        $mimeTypes = new MimeTypes();
        $extension = $mimeTypes->getExtensions($uploadedFile->getMimeType())[0] ?? $uploadedFile->getClientOriginalExtension();
        $newFilename = $safeFilename . '.' . $extension;

        $allowedExtensions = $this->getAllowedExtensions($newFilename);
        if (!in_array($extension, $allowedExtensions)) {
            return ['error' => 'File type not allowed', 'filename' => $uploadedFile->getClientOriginalName()];
        }

        try {
            $uploadedFile->move(
                $this->uploadDirectory,
                $newFilename
            );
        } catch (FileException $e) {
            return ['error' => 'Failed to upload file', 'filename' => $uploadedFile->getClientOriginalName()];
        }

        $upload = new Upload();
        $upload->setFileName($newFilename);
        $upload->setStatus('pending');

        $this->entityManager->persist($upload);
        $this->entityManager->flush();

        return [
            'message' => 'File uploaded successfully',
            'filename' => $newFilename
        ];
    }

    private function slugify($text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
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