<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Symfony\Contracts\EventDispatcher\Event;

class SchemaRetrievalEvent extends Event
{
    private ?array $schema;

    public function __construct()
    {
        $this->schema = null;
    }

    public function setSchema(array $schema): void
    {
        $this->schema = $schema;
    }

    public function getSchema(): ?array
    {
        return $this->schema;
    }
}
