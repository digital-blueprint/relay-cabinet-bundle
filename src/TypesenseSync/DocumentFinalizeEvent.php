<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Symfony\Contracts\EventDispatcher\Event;

class DocumentFinalizeEvent extends Event
{
    public function __construct(private array $document)
    {
    }

    public function getDocument(): array
    {
        return $this->document;
    }

    public function setDocument(array $document): void
    {
        $this->document = $document;
    }
}
