<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\PersonSync\PersonSyncInterface;
use Dbp\Relay\CabinetBundle\TypesenseClient\SearchIndex;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class TypesenseSync implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private CacheItemPoolInterface $cachePool;
    private SearchIndex $searchIndex;
    private PersonSyncInterface $personSync;
    private DocumentTranslator $translator;

    public function __construct(SearchIndex $searchIndex, PersonSyncInterface $personSync, DocumentTranslator $translator)
    {
        $this->cachePool = new ArrayAdapter();
        $this->searchIndex = $searchIndex;
        $this->personSync = $personSync;
        $this->logger = new NullLogger();
        $this->translator = $translator;
    }

    public function setCache(?CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;
    }

    private function getCursor(): ?string
    {
        $item = $this->cachePool->getItem('cursor');
        $cursor = null;
        if ($item->isHit()) {
            $cursor = $item->get();
        }

        return $cursor;
    }

    private function saveCursor(?string $cursor): void
    {
        $item = $this->cachePool->getItem('cursor');
        $item->set($cursor);
        $item->expiresAfter(3600 * 24);
        $this->cachePool->save($item);
    }

    public function sync(bool $full = false)
    {
        if ($full) {
            $this->saveCursor(null);
        }
        $cursor = $this->getCursor();

        // Process in chunks to reduce memory consumption
        $chunkSize = 10000;

        if ($cursor === null) {
            $this->logger->info('Starting a full sync');
            $schema = $this->translator->getSchema();

            $this->searchIndex->setSchema($schema);
            $this->searchIndex->ensureSetup();
            $this->searchIndex->deleteOldCollections();
            $collectionName = $this->searchIndex->createNewCollection();

            $res = $this->personSync->getAllPersons();
            foreach (array_chunk($res->getPersons(), $chunkSize) as $persons) {
                $documents = [];
                foreach ($persons as $person) {
                    $documents[] = $this->personToDocument($person);
                }
                $this->searchIndex->addDocumentsToCollection($collectionName, $documents);
            }

            $this->searchIndex->updateAlias($collectionName);
            $this->searchIndex->deleteOldCollections();

            $this->saveCursor($res->getCursor());
        } else {
            $this->logger->info('Starting a partial sync');
            $res = $this->personSync->getAllPersons($cursor);
            $collectionName = $this->searchIndex->getCollectionName();

            foreach (array_chunk($res->getPersons(), $chunkSize) as $persons) {
                $documents = [];
                foreach ($persons as $person) {
                    $documents[] = $this->personToDocument($person);
                }
                $this->searchIndex->addDocumentsToCollection($collectionName, $documents);
            }

            $this->saveCursor($res->getCursor());
        }
    }

    public function syncOne(string $id)
    {
        $this->logger->info('Syncing one person: '.$id);
        $cursor = $this->getCursor();
        $res = $this->personSync->getPersons([$id], $cursor);
        $documents = [];
        foreach ($res->getPersons() as $person) {
            $documents[] = $this->personToDocument($person);
        }
        $collectionName = $this->searchIndex->getCollectionName();
        $this->searchIndex->addDocumentsToCollection($collectionName, $documents);
        $this->saveCursor($res->getCursor());
    }

    public function upsertPartialFile(array $partialFileDocument): void
    {
        $collectionName = $this->searchIndex->getAliasName();

        $id = $partialFileDocument['base']['identNrObfuscated'];

        // Find the matching person, and copy over the base info
        $results = $this->searchIndex->findDocuments($collectionName, 'Person', 'base.identNrObfuscated', $id);
        if ($results) {
            $partialFileDocument['base'] = $results[0]['base'];
        } else {
            // FIXME: what if the person is missing? Just ignore the document until the next full sync, or poll later?
            // atm the schema needs those fields, so just skip for now
            $this->logger->warning('No person found in typesense matching '.$id.'. Skipping blob file');

            return;
        }

        $this->searchIndex->addDocumentsToCollection($collectionName, [$partialFileDocument]);
    }

    public function deletePartialFile(array $partialFileDocument): void
    {
        $collectionName = $this->searchIndex->getAliasName();
        $id = $partialFileDocument['id'];
        $this->searchIndex->deleteDocument($collectionName, $id);
    }

    public function personToDocument(array $person): array
    {
        return $this->translator->translateDocument('person', $person);
    }
}
