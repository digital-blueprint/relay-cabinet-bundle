<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseClient;

use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Http\Client\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Ulid;
use Typesense\Client;
use Typesense\Exceptions\TypesenseClientError as TypesenseClientErrorAlias;

class SearchIndex implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const COLLECTION_PREFIX = 'cabinet';

    private ConfigurationService $config;
    private Connection $connection;
    private array $schema;

    public function __construct(ConfigurationService $config)
    {
        $this->config = $config;
        $this->connection = new Connection($config->getTypesenseBaseUrl(), $config->getTypesenseApiKey());
        $this->logger = new NullLogger();
        $this->schema = [];
    }

    private function getClient(): Client
    {
        return $this->connection->getClient();
    }

    public function getAliasName(): string
    {
        return self::COLLECTION_PREFIX;
    }

    private function getCollectionPrefix(): string
    {
        return $this->getAliasName().'-';
    }

    public function setSchema(array $schema): void
    {
        $this->schema = $schema;
    }

    public function createNewCollection(): string
    {
        $schema = $this->schema;
        // We are using a new collection name based on the alias name with the current date
        $collectionName = $this->getCollectionPrefix().date('YmdHis').'-'.(new Ulid())->toBase32();
        // We are overwriting the collection name in the schema, so we can later create the alias with the correct name
        $schema['name'] = $collectionName;

        $this->logger->info("Creating new collection '$collectionName'");
        $this->getClient()->collections->create($schema);

        return $collectionName;
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
        $this->expireOldCollections(0);
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

    public function expireOldCollections(int $keepLast = 3): bool
    {
        $client = $this->getClient();
        // Don't delete the currently linked collection in all cases
        $alias = $client->aliases[$this->getAliasName()]->retrieve();
        $collectionNameSkipList = [$alias['collection_name']];

        // TODO: remove this once we are done testing
        // We use these for testing as well, so skip for now
        $collectionNameSkipList = array_merge(
            $collectionNameSkipList,
            ['cabinet-students', 'cabinet-files']
        );

        // Fetch all collections
        try {
            $collections = $client->collections->retrieve();
        } catch (Exception|TypesenseClientErrorAlias) {
            return false;
        }

        // Collect all collections with the given prefix that are not in the skip list
        $collectionNameList = [];
        foreach ($collections as $collection) {
            if (str_starts_with($collection['name'], $this->getCollectionPrefix())
                && !in_array($collection['name'], $collectionNameSkipList, true)) {
                $collectionNameList[] = $collection['name'];
            }
        }

        rsort($collectionNameList);
        // Slice off $keepLast collections
        $collectionNameList = array_slice($collectionNameList, $keepLast);

        // Delete the remaining collections
        foreach ($collectionNameList as $collectionName) {
            $this->logger->info("Deleting old collection '$collectionName'");
            try {
                $client->collections[$collectionName]->delete();
            } catch (Exception|TypesenseClientErrorAlias $e) {
                $this->logger->error('Deleting collection failed', ['exception' => $e]);
            }
        }

        return true;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->connection->setLogger($logger);
    }

    public function checkConnection(): void
    {
        $client = $this->connection->getClient();
        $client->getHealth()->retrieve();
        $client->getMultiSearch()->perform(['searches' => [['q' => 'healthcheck']]]);
    }
}
