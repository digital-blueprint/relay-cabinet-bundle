<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Symfony\Contracts\EventDispatcher\Event;

class SchemaRetrievalEvent extends Event
{
    private ?array $schema;

    private array $sharedFields;
    private ?string $personIdField;

    private ?string $documentIdField;

    private string $version;

    public function __construct()
    {
        $this->schema = null;
        $this->version = '';
        $this->sharedFields = [];
        $this->personIdField = null;
        $this->documentIdField = null;
    }

    public function setSchema(array $schema, array $sharedPersonFields, string $personIdField, string $documentIdField): void
    {
        $this->schema = $schema;
        $this->sharedFields = $sharedPersonFields;
        $this->personIdField = $personIdField;
        $this->documentIdField = $documentIdField;
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

    public function getSharedFields(): array
    {
        return $this->sharedFields;
    }

    public function getPersonIdField(): ?string
    {
        return $this->personIdField;
    }

    public function getDocumentIdField(): ?string
    {
        return $this->documentIdField;
    }
}
