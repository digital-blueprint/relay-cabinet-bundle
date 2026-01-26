<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\Service\ConfigurationService;

class CollectionManager
{
    public function __construct(private TypesenseClient $searchIndex, private ConfigurationService $config)
    {
    }

    public function getCursor(string $primaryCollectionName): ?string
    {
        $metadata = $this->searchIndex->getCollectionMetadata($primaryCollectionName);

        return $metadata['cabinet:syncCursor'] ?? null;
    }

    /**
     * Returns the time the collection was updated last. null if it was never updated so far.
     */
    public function getUpdatedAt(string $primaryCollectionName): ?\DateTimeInterface
    {
        $metadata = $this->searchIndex->getCollectionMetadata($primaryCollectionName);
        $updatedAt = $metadata['cabinet:updatedAt'] ?? null;
        if ($updatedAt === null) {
            return null;
        }

        return new \DateTimeImmutable($updatedAt);
    }

    public function getCreatedAt(string $primaryCollectionName): ?\DateTimeInterface
    {
        $metadata = $this->searchIndex->getCollectionMetadata($primaryCollectionName);
        $createdAt = $metadata['cabinet:createdAt'] ?? null;
        if ($createdAt === null) {
            return null;
        }

        return new \DateTimeImmutable($createdAt);
    }

    public function saveCursor(string $primaryCollectionName, ?string $cursor): void
    {
        $metadata = $this->searchIndex->getCollectionMetadata($primaryCollectionName);
        $metadata['cabinet:syncCursor'] = $cursor;
        $now = (new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')))->format(\DateTime::ATOM);
        $metadata['cabinet:updatedAt'] = $now;
        if (!array_key_exists('cabinet:createdAt', $metadata)) {
            $metadata['cabinet:createdAt'] = $now;
        }
        $this->searchIndex->setCollectionMetadata($primaryCollectionName, $metadata);
    }

    public function getPrimaryCollectionName(): string
    {
        return $this->searchIndex->getCollectionName();
    }

    /**
     * @return string[]
     */
    public function getAllAliases(): array
    {
        $primaryAlias = $this->searchIndex->getAliasName();
        $aliases = [];
        if ($this->config->getTypesenseSearchPartitionsSplitCollection()) {
            for ($index = 0; $index < $this->config->getTypesenseSearchPartitions(); ++$index) {
                if ($index === 0) {
                    $aliases[] = $primaryAlias;
                } else {
                    $aliases[] = $primaryAlias.'-'.$index;
                }
            }
        } else {
            $aliases[] = $primaryAlias;
        }

        return $aliases;
    }

    /**
     * Returns all possible collection names for a primary name.
     *
     * @return string[]
     */
    public function getAllCollectionNames(string $primaryCollectionName): array
    {
        $names = [];
        if ($this->config->getTypesenseSearchPartitionsSplitCollection()) {
            for ($index = 0; $index < $this->config->getTypesenseSearchPartitions(); ++$index) {
                if ($index === 0) {
                    $names[] = $primaryCollectionName;
                } else {
                    $names[] = $primaryCollectionName.'-'.$index;
                }
            }
        } else {
            $names[] = $primaryCollectionName;
        }

        return $names;
    }

    public function getCollectionNameForDocument(string $primaryCollectionName, array $document)
    {
        if ($this->config->getTypesenseSearchPartitionsSplitCollection()) {
            $partitionKey = $document['partitionKey'];
            $index = Utils::getPartitionIndex($this->config->getTypesenseSearchPartitions(), $partitionKey, 100);
            if ($index === 0) {
                $name = $primaryCollectionName;
            } else {
                $name = $primaryCollectionName.'-'.$index;
            }

            return $name;
        }

        return $primaryCollectionName;
    }

    public function updateAliases(string $primaryCollectionName)
    {
        $mapping = array_combine($this->getAllAliases(), $this->getAllCollectionNames($primaryCollectionName));
        foreach ($mapping as $alias => $collectionName) {
            $this->searchIndex->updateAlias($collectionName, $alias);
        }
    }

    public function deleteOldCollections(): void
    {
        $primaryCollectionName = $this->getPrimaryCollectionName();
        $this->searchIndex->deleteAllCollections($this->getAllCollectionNames($primaryCollectionName));
    }

    public function createNewCollections(array $schema): string
    {
        $primaryCollectionName = $this->searchIndex->createNewCollection($schema);
        foreach ($this->getAllCollectionNames($primaryCollectionName) as $collectionName) {
            if ($primaryCollectionName === $collectionName) {
                continue;
            }
            $this->searchIndex->createNewCollection($schema, $collectionName);
        }

        return $primaryCollectionName;
    }
}
