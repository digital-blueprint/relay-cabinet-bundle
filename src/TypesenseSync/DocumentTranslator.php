<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DocumentTranslator
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

        return $event->getSchema() ?? self::DEFAULT_SCHEMA;
    }

    /**
     * Translates a document to a document matching typesense schema.
     */
    public function translateDocument(string $objectType, array $document): array
    {
        $event = new DocumentTranslationEvent($objectType, $document);
        $event = $this->eventDispatcher->dispatch($event);

        return $event->getTranslatedDocument() ?? $document;
    }
}
