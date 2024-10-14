<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Symfony\Contracts\EventDispatcher\Event;

class DocumentTranslationEvent extends Event
{
    private ?array $translatedDocuments;

    public function __construct(private string $objectType, private array $document)
    {
        $this->translatedDocuments = null;
    }

    public function getDocument(): array
    {
        return $this->document;
    }

    public function setTranslatedDocument(array $translatedDocument): void
    {
        $this->translatedDocuments = [$translatedDocument];
    }

    public function setTranslatedDocuments(array $translatedDocuments): void
    {
        $this->translatedDocuments = $translatedDocuments;
    }

    public function getTranslatedDocuments(): ?array
    {
        return $this->translatedDocuments;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }
}
