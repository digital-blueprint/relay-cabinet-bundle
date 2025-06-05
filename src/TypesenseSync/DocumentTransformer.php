<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DocumentTransformer
{
    private EventDispatcherInterface $eventDispatcher;

    private const DEFAULT_SCHEMA = [
        'name' => 'default',
        'enable_nested_fields' => true,
        'fields' => [
            // Typesense needs at least one field
            ['name' => 'objectType', 'type' => 'string', 'optional' => false, 'facet' => false, 'sort' => true],
        ],
    ];

    private ?SchemaRetrievalEvent $schemaEvent;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->schemaEvent = null;
    }

    private function ensureSchema(): SchemaRetrievalEvent
    {
        if ($this->schemaEvent === null) {
            $event = new SchemaRetrievalEvent();
            $event = $this->eventDispatcher->dispatch($event);
            $this->schemaEvent = $event;
        }

        return $this->schemaEvent;
    }

    public function getSchema(): array
    {
        $event = $this->ensureSchema();
        $schema = $event->getSchema() ?? self::DEFAULT_SCHEMA;
        $now = (new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')))->format(\DateTime::ATOM);
        $metadata['cabinet:createdAt'] = $now;
        $metadata['cabinet:schemaVersion'] = $event->getSchemaVersion();
        $schema['metadata'] = $metadata;
        $schema['fields'][] = ['name' => 'partitionKey', 'type' => 'int32', 'optional' => false, 'facet' => false, 'sort' => false, 'range_index' => true];

        return $schema;
    }

    /**
     * Given an existing schema, returns if the schema is still current, or if the collection has to be re-created
     * with a new schema.
     */
    public function isSchemaOutdated(array $metadata): bool
    {
        $event = $this->ensureSchema();

        return ($metadata['cabinet:schemaVersion'] ?? null) !== $event->getSchemaVersion();
    }

    /**
     * Transform a document to zero or more documents matching the typesense schema.
     */
    public function transformDocument(string $objectType, array $document): array
    {
        $event = new DocumentTransformEvent($objectType, $document);
        $event = $this->eventDispatcher->dispatch($event);

        return $event->getTransformedDocuments() ?? [$document];
    }

    /**
     * Returns a random number between 0-99 for a given ID.
     */
    public static function getPartitionKey(string $id): int
    {
        return abs(crc32($id)) % 100;
    }

    /**
     * Called with the complete/updated/merged document right before sending it to typesense.
     *
     * Allows users to modify/update fields one last time.
     */
    public function finalizeDocument(array $document): array
    {
        $event = new DocumentFinalizeEvent($document);
        $event = $this->eventDispatcher->dispatch($event);
        $document = $event->getDocument();
        $document['partitionKey'] = self::getPartitionKey(Utils::getField($document, $this->getPersonIdField()));

        return $document;
    }

    public function getSharedFields(): array
    {
        $event = $this->ensureSchema();

        return $event->getSharedFields();
    }

    public function getPersonIdField(): string
    {
        $event = $this->ensureSchema();

        return $event->getPersonIdField() ?? 'person.id';
    }

    public function getDocumentIdField(): string
    {
        $event = $this->ensureSchema();

        return $event->getDocumentIdField() ?? 'document.id';
    }
}
