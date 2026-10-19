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
        private readonly ?string $newType)
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
