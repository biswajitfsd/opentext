<?php

namespace App\Entity;

use App\Repository\UploadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UploadRepository::class)]
class Upload
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    private ?int $vulnerabilityCount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $debrickedUploadId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $repositoryId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commitId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getVulnerabilityCount(): ?int
    {
        return $this->vulnerabilityCount;
    }

    public function setVulnerabilityCount(?int $count): self
    {
        $this->vulnerabilityCount = $count;
        return $this;
    }

    public function getDebrickedUploadId(): ?string
    {
        return $this->debrickedUploadId;
    }

    public function setDebrickedUploadId(?string $debrickedUploadId): self
    {
        $this->debrickedUploadId = $debrickedUploadId;
        return $this;
    }

    public function getRepositoryId(): ?string
    {
        return $this->repositoryId;
    }

    public function setRepositoryId(?string $repositoryId): self
    {
        $this->repositoryId = $repositoryId;
        return $this;
    }

    public function getCommitId(): ?string
    {
        return $this->commitId;
    }

    public function setCommitId(?string $commitId): self
    {
        $this->commitId = $commitId;
        return $this;
    }
}