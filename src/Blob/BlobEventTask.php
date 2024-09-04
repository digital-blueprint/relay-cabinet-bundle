<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

class BlobEventTask
{
    public function __construct(private string $action, private string $bucketId, private string $fileId)
    {
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getBucketId(): string
    {
        return $this->bucketId;
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }
}
