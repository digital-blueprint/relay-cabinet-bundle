<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Symfony\Contracts\EventDispatcher\Event;

class DocumentTranslationEvent extends Event
{
    private ?array $translatedDocument;

    public function __construct(private string $objectType, private array $document)
    {
        $this->translatedDocument = null;
    }

    public function getDocument(): array
    {
        return $this->document;
    }

    public function setTranslatedDocument(array $translatedDocument): void
    {
        $this->translatedDocument = $translatedDocument;
    }

    public function getTranslatedDocument(): ?array
    {
        return $this->translatedDocument;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }
}
