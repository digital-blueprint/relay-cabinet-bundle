<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;

class TypesenseClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const COLLECTION_NAMESPACE = 'cabinet';

    private ConfigurationService $config;
    private TypesenseConnection $connection;

    public function __construct(ConfigurationService $config)
    {
        $this->config = $config;
        $this->connection = new TypesenseConnection($config->getTypesenseApiUrl(), $config->getTypesenseApiKey());
        $this->logger = new NullLogger();
    }

    public function setHttpClient(HttpClientInterface $client): void
    {
        $this->connection->setHttpClient($client);
    }

    private function getClient(): Client
    {
        return $this->connection->getClient();
    }

    public function getAliasName(): string
    {
        return self::COLLECTION_NAMESPACE;
    }

    public function getConnectionBaseUrl(): string
    {
        return $this->connection->getBaseUrl();
    }

    public function createNewCollection(?array $schema = null, ?string $name = null): string
    {
        // We are using a new collection name based on the alias name with the current date
        if ($name !== null) {
            $collectionName = $name;
        } else {
            $collectionName = self::COLLECTION_NAMESPACE.'-'.date('YmdHis').'-'.(new Ulid())->toBase32();
        }
        // Typesense requires a non-empty schema, so create one that auto-detects all fields
        if ($schema === null) {
            $schema = ['fields' => [['name' => '.*', 'type' => 'auto']]];
        }
        // We are overwriting the collection name in the schema, so we can later create the alias with the correct name
        $schema['name'] = $collectionName;

        $this->logger->info("Creating new collection '$collectionName'");
        $this->getClient()->collections->create($schema);

        return $collectionName;
    }

    public function getCollectionMetadata(string $collectionName): array
    {
        $schema = $this->getClient()->collections[$collectionName]->retrieve();

        return $schema['metadata'] ?? [];
    }

    public function setCollectionMetadata(string $collectionName, array $metadata): void
    {
        $collection = $this->getClient()->collections[$collectionName];
        $collection->update(['metadata' => $metadata]);
    }

    public function getCollectionName(): string
    {
        $client = $this->getClient();
        $alias = $client->aliases[$this->getAliasName()]->retrieve();

        return $alias['collection_name'];
    }

    public function needsSetup(string $alias): bool
    {
        // Either the alias doesn't exist, or the collection it references
        return !$this->getClient()->collections[$alias]->exists();
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

    public function getBaseMapping(string $collectionName, string $type, string $groupBy, array $includeFields)
    {
        if (preg_match('/\s/', $type) || preg_match('/\s/', $groupBy)) {
            throw new \RuntimeException('no whitespace supported');
        }
        foreach ($includeFields as $include) {
            if (preg_match('/\s/', $include)) {
                throw new \RuntimeException('no whitespace supported');
            }
        }
        $filterBy = '@type := '.$type;
        $lines = $this->getClient()->collections[$collectionName]->documents->export(['filter_by' => $filterBy, 'include_fields' => implode(',', $includeFields)]);
        $mapping = [];
        foreach (Utils::decodeJsonLines($lines, true) as $decoded) {
            $id = Utils::getField($decoded, $groupBy);
            $mapping[$id] = [];
            foreach ($includeFields as $include) {
                $mapping[$id][$include] = Utils::getField($decoded, $include);
            }
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
        $documents = [];
        foreach (Utils::decodeJsonLines($lines, true) as $decoded) {
            $documents[] = $decoded;
        }

        return $documents;
    }

    /**
     * Returns the document, or null if it was not found.
     */
    public function getDocument(string $collectionName, string $id): ?array
    {
        try {
            return $this->getClient()->collections[$collectionName]->documents[$id]->retrieve();
        } catch (ObjectNotFound) {
            return null;
        }
    }

    public function deleteDocument(string $collectionName, string $id): void
    {
        $this->getClient()->collections[$collectionName]->documents[$id]->delete();
    }

    public function updateAlias(string $collectionName, string $alias): void
    {
        $this->logger->info("Updating '$alias' to point to '$collectionName'");
        // Create/Overwrite the alias with the new collection
        $this->getClient()->aliases->upsert($alias, ['collection_name' => $collectionName]);
    }

    public function clearSearchCache(): void
    {
        $this->logger->info('Clearing search cache');
        $client = $this->getClient();
        $client->operations->perform('cache/clear');
    }

    /**
     * Ensures the proxy API keys for the alias are registered and deletes outdated keys.
     *
     * @param string[] $aliases - the aliases the key has access to
     */
    public function updateProxyApiKeys(array $aliases): void
    {
        $schemas = [
            [
                'description' => $this->config->getTypesenseProxyApiKey(),
                'actions' => [
                    'documents:search',
                ],
                'collections' => $aliases,
                'value' => $this->config->getTypesenseProxyApiKey(),
            ],
        ];

        $keyNeedsUpdate = function (array $key, array $schema): bool {
            if ($key['description'] !== $schema['description']
                || $key['collections'] !== $schema['collections']
                || $key['actions'] !== $schema['actions']
                || !str_starts_with($schema['value'], $key['value_prefix'])) {
                return true;
            }

            return false;
        };

        $this->logger->info('Re-creating keys if needed');
        $client = $this->getClient();
        $keys = $client->keys->retrieve();

        // All keys that are for our alias
        $aliasKeys = [];
        foreach ($keys['keys'] as $key) {
            foreach ($aliases as $aliasName) {
                if (in_array($aliasName, $key['collections'], true)) {
                    $aliasKeys[] = $key;
                }
            }
        }

        // See which keys are outdated
        $toDelete = [];
        $toAdd = [];
        foreach ($aliasKeys as $aliasKey) {
            $found = false;
            foreach ($schemas as $schema) {
                if ($schema['description'] === $aliasKey['description']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $toDelete[] = $aliasKey;
            }
        }

        foreach ($schemas as $schema) {
            $found = false;
            foreach ($aliasKeys as $aliasKey) {
                if ($schema['description'] === $aliasKey['description']) {
                    if ($keyNeedsUpdate($aliasKey, $schema)) {
                        $toDelete[] = $aliasKey;
                        $toAdd[] = $schema;
                    }
                    $found = true;
                }
            }
            if (!$found) {
                $toAdd[] = $schema;
            }
        }

        // Delete and add keys if needed
        foreach ($toDelete as $aliasKey) {
            $this->logger->info('Deleting outdated key: id='.$aliasKey['id']);
            $client->keys[$aliasKey['id']]->delete();
        }
        foreach ($toAdd as $schema) {
            $this->logger->info('Creating a new key: description='.$schema['description']);
            $key = $client->keys->create($schema);
            $this->logger->info('Created new key '.$key['id']);
        }
    }

    /**
     * Delete all collections that are no longer actively used and all aliases that referenced them.
     */
    public function deleteOldCollections(array $collectionsToSkip): void
    {
        $client = $this->getClient();

        // Collect all collections with the given prefix that are not in the skip list
        $collections = $client->collections->retrieve();
        $collectionNameList = [];
        foreach ($collections as $collection) {
            if (str_starts_with($collection['name'], self::COLLECTION_NAMESPACE.'-')
                && !in_array($collection['name'], $collectionsToSkip, true)) {
                $collectionNameList[] = $collection['name'];
            }
        }

        // Delete the remaining collections
        foreach ($collectionNameList as $collectionName) {
            $this->logger->info("Deleting old collection '$collectionName'");
            $client->collections[$collectionName]->delete();
        }

        // Delete tangling aliases
        $aliases = $client->aliases->retrieve()['aliases'];
        foreach ($aliases as $alias) {
            $aliasName = $alias['name'];
            if ($aliasName === self::COLLECTION_NAMESPACE || str_starts_with($aliasName, self::COLLECTION_NAMESPACE.'-')) {
                if (!$client->collections[$aliasName]->exists()) {
                    $client->aliases[$aliasName]->delete();
                }
            }
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
