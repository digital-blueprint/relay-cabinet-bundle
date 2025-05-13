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

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Returns a typesense schema.
     */
    public function getSchema(): array
    {
        $event = new SchemaRetrievalEvent();
        $event = $this->eventDispatcher->dispatch($event);

        $schema = $event->getSchema() ?? self::DEFAULT_SCHEMA;
        $now = (new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')))->format(\DateTime::ATOM);
        $metadata['cabinet:createdAt'] = $now;
        $metadata['cabinet:schemaVersion'] = $event->getSchemaVersion();
        $schema['metadata'] = $metadata;

        return $schema;
    }

    public function getSchemaFields(): array
    {
        $event = new SchemaRetrievalEvent();
        $event = $this->eventDispatcher->dispatch($event);

        return [$event->getSharedFields(), $event->getPersonIdField() ?? 'person.id', $event->getDocumentIdField() ?? 'document.id'];
    }

    /**
     * Given an existing schema, returns if the schema is still current, or if the collection has to be re-created
     * with a new schema.
     */
    public function isSchemaOutdated(array $metadata): bool
    {
        $event = new SchemaRetrievalEvent();
        $event = $this->eventDispatcher->dispatch($event);

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
     * Called with the complete/updated/merged document right before sending it to typesense.
     *
     * Allows users to modify/update fields one last time.
     */
    public function finalizeDocument(array $document): array
    {
        $event = new DocumentFinalizeEvent($document);
        $event = $this->eventDispatcher->dispatch($event);

        return $event->getDocument();
    }
}
