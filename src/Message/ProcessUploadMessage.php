<?php

namespace App\Message;

class ProcessUploadMessage
{
    private $uploadId;

    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    public function getUploadId(): int
    {
        return $this->uploadId;
    }
}