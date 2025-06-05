<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
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

    public function createNewCollection(?array $schema = null): string
    {
        // We are using a new collection name based on the alias name with the current date
        $collectionName = $this->getCollectionPrefix().date('YmdHis').'-'.(new Ulid())->toBase32();
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
        $lines = explode("\n", $lines);

        $mapping = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
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
        $newName = $this->createNewCollection();
        $this->updateAlias($newName);
        $this->deleteOldCollections();
    }

    protected function isAliasExists(string $aliasName): bool
    {
        try {
            $this->getClient()->aliases[$aliasName]->retrieve();
        } catch (ClientExceptionInterface|TypesenseClientErrorAlias) {
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
        $this->updateProxyApiKeys();
    }

    public function clearSearchCache(): void
    {
        $this->logger->info('Clearing search cache');
        $client = $this->getClient();
        $client->operations->perform('cache/clear');
    }

    /**
     * Ensures the proxy API keys for the alias are registered and deletes outdated keys.
     */
    private function updateProxyApiKeys(): void
    {
        $aliasName = $this->getAliasName();
        $schemas = [
            [
                'description' => $this->config->getTypesenseProxyApiKey(),
                'actions' => [
                    'documents:get',
                    'documents:search',
                ],
                'collections' => [$aliasName],
                'value' => $this->config->getTypesenseProxyApiKey(),
            ],
            [
                'description' => $this->config->getTypesenseProxyApiSearchKey(),
                'actions' => [
                    'documents:search',
                ],
                'collections' => [$aliasName],
                'value' => $this->config->getTypesenseProxyApiSearchKey(),
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
            if (in_array($aliasName, $key['collections'], true)) {
                $aliasKeys[] = $key;
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
