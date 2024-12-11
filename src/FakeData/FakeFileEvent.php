<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\FakeData;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event which gets send out and lets users fill out fake file content and metadata.
 * Gets used by @AddFakeFilesCommand for generating fake files for blob or typesense.
 */
class FakeFileEvent extends Event
{
    private ?string $metadata = null;

    private ?string $filePath = null;

    private ?string $blobType = null;

    private ?string $fileName = null;

    public function __construct(private readonly int $number, private readonly int $totalNumber, private string $personId)
    {
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getTotalNumber(): int
    {
        return $this->totalNumber;
    }

    public function getPersonId(): string
    {
        return $this->personId;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public function getBlobType(): ?string
    {
        return $this->blobType;
    }

    public function setBlobType(string $blobType): void
    {
        $this->blobType = $blobType;
    }

    public function getMetadata(): ?string
    {
        return $this->metadata;
    }

    public function setMetadata(string $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }
}
