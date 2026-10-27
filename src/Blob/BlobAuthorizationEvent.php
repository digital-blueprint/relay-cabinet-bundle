<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use Symfony\Contracts\EventDispatcher\Event;

class BlobAuthorizationEvent extends Event
{
    private bool $accessGranted = true;

    public function __construct(
        private readonly string $method,
        private readonly ?string $identifier,
        private readonly mixed $oldMetadata,
        private readonly mixed $newMetadata,
        private readonly ?string $oldType,
        private readonly ?string $newType,
        private readonly ?string $oldPrefix = null,
        private readonly ?string $newPrefix = null,
        private readonly bool $hasOldDeleteAt = false,
        private readonly bool $hasNewDeleteAt = false,
        private readonly bool $fileUpdateRequested = false)
    {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getOldMetadata(): mixed
    {
        return $this->oldMetadata;
    }

    public function getNewMetadata(): mixed
    {
        return $this->newMetadata;
    }

    public function getOldType(): ?string
    {
        return $this->oldType;
    }

    public function getNewType(): ?string
    {
        return $this->newType;
    }

    public function getType(): ?string
    {
        return $this->getNewType() ?? $this->oldType;
    }

    public function getOldPrefix(): ?string
    {
        return $this->oldPrefix;
    }

    public function getNewPrefix(): ?string
    {
        return $this->newPrefix;
    }

    public function hasOldDeleteAt(): bool
    {
        return $this->hasOldDeleteAt;
    }

    public function hasNewDeleteAt(): bool
    {
        return $this->hasNewDeleteAt;
    }

    public function isFileUpdateRequested(): bool
    {
        return $this->fileUpdateRequested;
    }

    public function grantAccess(): void
    {
        $this->accessGranted = true;
    }

    public function denyAccess(): void
    {
        $this->accessGranted = false;
        $this->stopPropagation();
    }

    public function isAccessGranted(): bool
    {
        return $this->accessGranted;
    }
}
