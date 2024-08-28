<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

class TypesenseTask
{
    public function __construct(private string $action, private array $document)
    {
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getDocument(): array
    {
        return $this->document;
    }
}
