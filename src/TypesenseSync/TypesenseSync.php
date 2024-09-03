<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\PersonSync\PersonSyncInterface;
use Dbp\Relay\CabinetBundle\Service\BlobService;
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
    private BlobService $blobService;

    // Chunk processing to reduce memory consumption
    private const CHUNK_SIZE = 10000;

    public function __construct(SearchIndex $searchIndex, PersonSyncInterface $personSync, DocumentTranslator $translator, BlobService $blobService)
    {
        $this->cachePool = new ArrayAdapter();
        $this->searchIndex = $searchIndex;
        $this->personSync = $personSync;
        $this->logger = new NullLogger();
        $this->translator = $translator;
        $this->blobService = $blobService;
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

    /**
     * Sync all files from blob into typesense. Needs to be called after all persons have already been synced.
     */
    public function upsertAllFiles(string $collectionName): void
    {
        $this->logger->info('Syncing all blob files');

        $this->logger->info('Fetch mapping for base data');
        // First we get a mapping of the base ID to the base content for all Persons in typesense
        $baseMapping = $this->searchIndex->getBaseMapping($collectionName, 'Person', 'identNrObfuscated', 'base');
        $this->logger->debug('Base entries found: '.count($baseMapping));

        // Then we fetch all files from the blob bucket, translate it to the typsensese schema, and enrich it
        // with the base data of the persons from the mapping above.
        // In case there is no corresponding person in typesense we simply drop the file atm.
        // In the end we upsert everything to typesense.
        $newDocuments = [];
        $notFound = [];
        $bucketId = $this->blobService->getBucketId();
        $documentCount = 0;
        foreach ($this->blobService->getAllFiles() as $fileData) {
            $metadata = json_decode($fileData['metadata'], associative: true, flags: JSON_THROW_ON_ERROR);
            $objectType = $metadata['objectType'];
            $input = [
                'id' => $fileData['identifier'],
                'fileSource' => $bucketId,
                'fileName' => $fileData['fileName'],
                'metadata' => $metadata,
            ];
            $translated = $this->translator->translateDocument($objectType, $input);

            $id = $translated['base']['identNrObfuscated'];
            // XXX: If the related person isn't in typesense, we just ignore the file
            if (!array_key_exists($id, $baseMapping)) {
                if (!array_key_exists($id, $notFound)) {
                    $this->logger->warning('For file '.$fileData['identifier'].' (and possibly more) with baseId "'.$id.'" no matching base data found, skipping');
                    $notFound[$id] = null;
                }
                continue;
            }
            $translated['base'] = $baseMapping[$id];
            $newDocuments[] = $translated;
            ++$documentCount;
            if (count($newDocuments) > self::CHUNK_SIZE) {
                $this->searchIndex->addDocumentsToCollection($collectionName, $newDocuments);
                $newDocuments = [];
            }
        }
        $this->searchIndex->addDocumentsToCollection($collectionName, $newDocuments);
        $this->logger->info('Upserted '.$documentCount.' file documents into typesense');
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

        if ($cursor === null) {
            $this->logger->info('Starting a full sync');
            $schema = $this->translator->getSchema();

            $this->searchIndex->setSchema($schema);
            $this->searchIndex->ensureSetup();
            $this->searchIndex->deleteOldCollections();
            $collectionName = $this->searchIndex->createNewCollection();

            $res = $this->personSync->getAllPersons();
            foreach (array_chunk($res->getPersons(), self::CHUNK_SIZE) as $persons) {
                $documents = [];
                foreach ($persons as $person) {
                    $documents[] = $this->personToDocument($person);
                }
                $this->searchIndex->addDocumentsToCollection($collectionName, $documents);
            }

            $this->upsertAllFiles($collectionName);

            $this->searchIndex->updateAlias($collectionName);
            $this->searchIndex->deleteOldCollections();

            $this->saveCursor($res->getCursor());
        } else {
            $this->logger->info('Starting a partial sync');
            $res = $this->personSync->getAllPersons($cursor);
            $collectionName = $this->searchIndex->getCollectionName();

            foreach (array_chunk($res->getPersons(), self::CHUNK_SIZE) as $persons) {
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
