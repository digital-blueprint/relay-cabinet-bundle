<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Symfony\Contracts\EventDispatcher\Event;

class SchemaRetrievalEvent extends Event
{
    private ?array $schema;

    private string $version;

    public function __construct()
    {
        $this->schema = null;
        $this->version = '';
    }

    public function setSchema(array $schema): void
    {
        $this->schema = $schema;
    }

    public function getSchema(): ?array
    {
        return $this->schema;
    }

    public function setSchemaVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getSchemaVersion(): string
    {
        return $this->version;
    }
}
