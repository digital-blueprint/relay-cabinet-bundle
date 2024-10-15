<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Symfony\Contracts\EventDispatcher\Event;

class DocumentTransformationEvent extends Event
{
    private ?array $transformedDocuments;

    public function __construct(private string $objectType, private array $document)
    {
        $this->transformedDocuments = null;
    }

    public function getDocument(): array
    {
        return $this->document;
    }

    public function setTransformedDocuments(array $transformedDocuments): void
    {
        $this->transformedDocuments = $transformedDocuments;
    }

    public function getTransformedDocuments(): ?array
    {
        return $this->transformedDocuments;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }
}
