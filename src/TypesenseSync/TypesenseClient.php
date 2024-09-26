<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Http\Client\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Ulid;
use Typesense\Client;
use Typesense\Exceptions\TypesenseClientError as TypesenseClientErrorAlias;

class TypesenseClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const COLLECTION_PREFIX = 'cabinet';

    private ConfigurationService $config;
    private TypesenseConnection $connection;

    public function __construct(ConfigurationService $config)
    {
        $this->config = $config;
        $this->connection = new TypesenseConnection($config->getTypesenseApiUrl(), $config->getTypesenseApiKey());
        $this->logger = new NullLogger();
    }

    private function getClient(): Client
    {
        return $this->connection->getClient();
    }

    public function getAliasName(): string
    {
        return self::COLLECTION_PREFIX;
    }

    public function getConnectionBaseUrl(): string
    {
        return $this->connection->getBaseUrl();
    }

    private function getCollectionPrefix(): string
    {
        return $this->getAliasName().'-';
    }

    public function createNewCollection(array $schema): string
    {
        // We are using a new collection name based on the alias name with the current date
        $collectionName = $this->getCollectionPrefix().date('YmdHis').'-'.(new Ulid())->toBase32();
        // We are overwriting the collection name in the schema, so we can later create the alias with the correct name
        $schema['name'] = $collectionName;

        $this->logger->info("Creating new collection '$collectionName'");
        $this->getClient()->collections->create($schema);

        return $collectionName;
    }

    public function getSchemaMetadata(string $collectionName): array
    {
        $schema = $this->getClient()->collections[$collectionName]->retrieve();

        return $schema['metadata'] ?? [];
    }

    public function getCollectionName(): string
    {
        $client = $this->getClient();
        $alias = $client->aliases[$this->getAliasName()]->retrieve();

        return $alias['collection_name'];
    }

    public function addDocumentsToCollection(string $collectionName, array $documents): void
    {
        if ($documents === []) {
            return;
        }
        // See https://typesense.org/docs/guide/syncing-data-into-typesense.html
        $responses = $this->getClient()->collections[$collectionName]->documents->import($documents, ['action' => 'upsert']);
        assert(is_array($responses));

        // We need to do our own error handling here, see https://typesense.org/docs/0.25.1/api/documents.html#index-multiple-documents
        foreach ($responses as $response) {
            if ($response['success'] === false) {
                $this->logger->error('Failed to import document', ['reponse' => $response]);
            }
        }
    }

    public function getBaseMapping(string $collectionName, string $type, string $key, string $include)
    {
        if (preg_match('/\s/', $include) || preg_match('/\s/', $type) || preg_match('/\s/', $key)) {
            throw new \RuntimeException('no whitespace supported');
        }
        $filterBy = '@type := '.$type;
        $lines = $this->getClient()->collections[$collectionName]->documents->export(['filter_by' => $filterBy, 'include_fields' => $include]);
        $lines = explode("\n", $lines);
        $mapping = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $id = $decoded[$include][$key];
            $mapping[$id] = $decoded['base'];
        }

        return $mapping;
    }

    /**
     * Returns all documents of type $type where $key matches $value.
     */
    public function findDocuments(string $collectionName, string $type, string $key, string $value): array
    {
        if (preg_match('/\s/', $value) || preg_match('/\s/', $type)) {
            throw new \RuntimeException('no whitespace supported');
        }
        $filterBy = $key.':='.$value.' && @type := '.$type;
        $lines = $this->getClient()->collections[$collectionName]->documents->export(['filter_by' => $filterBy]);
        if ($lines === '') {
            return [];
        }
        $lines = explode("\n", $lines);
        $documents = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $documents[] = $decoded;
        }

        return $documents;
    }

    public function deleteDocument(string $collectionName, string $id): void
    {
        $this->getClient()->collections[$collectionName]->documents[$id]->delete();
    }

    public function updateAlias(string $collectionName): void
    {
        $aliasName = $this->getAliasName();
        $this->logger->info("Updating '$aliasName' to point to '$collectionName'");
        // Create/Overwrite the alias with the new collection
        $this->getClient()->aliases->upsert($aliasName, ['collection_name' => $collectionName]);
    }

    /**
     * Purge all collection data, only leaving an empty collection and an alias pointing to it.
     */
    public function purgeAll(): void
    {
        $newName = $this->createNewCollection([]);
        $this->updateAlias($newName);
        $this->deleteOldCollections();
    }

    protected function isAliasExists(string $aliasName): bool
    {
        try {
            $this->getClient()->aliases[$aliasName]->retrieve();
        } catch (Exception|TypesenseClientErrorAlias) {
            return false;
        }

        return true;
    }

    public function ensureSetup(): void
    {
        $this->logger->info('Running setup');
        $aliasName = $this->getAliasName();
        if (!$this->isAliasExists($aliasName)) {
            $this->logger->info('No alias found, creating empty collection and alias');
            $this->purgeAll();
        }
    }

    /**
     * Delete all collections that are no longer actively used.
     */
    public function deleteOldCollections(): void
    {
        $client = $this->getClient();
        $collectionNameSkipList = [];

        // Don't delete the currently linked collection in all cases
        $alias = $client->aliases[$this->getAliasName()]->retrieve();
        $collectionNameSkipList[] = $alias['collection_name'];

        // Collect all collections with the given prefix that are not in the skip list
        $collections = $client->collections->retrieve();
        $collectionNameList = [];
        foreach ($collections as $collection) {
            if (str_starts_with($collection['name'], $this->getCollectionPrefix())
                && !in_array($collection['name'], $collectionNameSkipList, true)) {
                $collectionNameList[] = $collection['name'];
            }
        }

        // Delete the remaining collections
        foreach ($collectionNameList as $collectionName) {
            $this->logger->info("Deleting old collection '$collectionName'");
            $client->collections[$collectionName]->delete();
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->connection->setLogger($logger);
    }

    public function checkConnection(): void
    {
        // Limit retries, so we fail more quickly
        $client = $this->connection->getClient(3);
        $client->getHealth()->retrieve();
        $client->getMultiSearch()->perform(['searches' => [['q' => 'healthcheck']]]);
    }
}
