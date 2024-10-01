<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\Blob\BlobService;
use Dbp\Relay\CabinetBundle\PersonSync\PersonSyncInterface;
use Dbp\Relay\CabinetBundle\PersonSync\PersonSyncResultInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class TypesenseSync implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private CacheItemPoolInterface $cachePool;
    private TypesenseClient $searchIndex;
    private PersonSyncInterface $personSync;
    private DocumentTranslator $translator;
    private BlobService $blobService;

    // Chunk processing to reduce memory consumption
    private const CHUNK_SIZE = 10000;
    private LockFactory $lockFactory;

    public function __construct(TypesenseClient $searchIndex, PersonSyncInterface $personSync, DocumentTranslator $translator, BlobService $blobService, LockFactory $lockFactory)
    {
        $this->cachePool = new ArrayAdapter();
        $this->searchIndex = $searchIndex;
        $this->personSync = $personSync;
        $this->logger = new NullLogger();
        $this->translator = $translator;
        $this->blobService = $blobService;
        $this->lockFactory = $lockFactory;
    }

    public function getConnectionBaseUrl(): string
    {
        return $this->searchIndex->getConnectionBaseUrl();
    }

    public function getSyncLockKey(): Key
    {
        return new Key('cabinet-sync');
    }

    public function setCache(?CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;
    }

    private function getCursor(string $collectionName): ?string
    {
        $item = $this->cachePool->getItem($collectionName.'.cursor');
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
        $documentCount = 0;
        foreach ($this->blobService->getAllFiles() as $fileData) {
            $translated = $this->fileDataToPartialDocument($fileData);
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

    public function upsertFile(string $blobFileId): void
    {
        $collectionName = $this->searchIndex->getCollectionName();
        $fileData = $this->blobService->getFile($blobFileId);
        $partialFileDocument = $this->fileDataToPartialDocument($fileData);

        $blobFileId = $partialFileDocument['base']['identNrObfuscated'];
        $results = $this->searchIndex->findDocuments($collectionName, 'Person', 'base.identNrObfuscated', $blobFileId);
        if ($results) {
            $partialFileDocument['base'] = $results[0]['base'];
        } else {
            // FIXME: what if the person is missing? Just ignore the document until the next full sync, or poll later?
            // atm the schema needs those fields, so just skip for now
            $this->logger->warning('No person found in typesense matching '.$blobFileId.'. Skipping blob file');

            return;
        }
        $this->searchIndex->addDocumentsToCollection($collectionName, [$partialFileDocument]);
    }

    public function deleteFile(string $blobFileId): void
    {
        $collectionName = $this->searchIndex->getCollectionName();
        $results = $this->searchIndex->findDocuments($collectionName, 'DocumentFile', 'file.base.fileId', $blobFileId);
        foreach ($results as $result) {
            $typesenseId = $result['id'];
            $this->searchIndex->deleteDocument($collectionName, $typesenseId);
        }
    }

    private function saveCursor(string $collectionName, ?string $cursor): void
    {
        $item = $this->cachePool->getItem($collectionName.'.cursor');
        $item->set($cursor);
        $item->expiresAfter(3600 * 24);
        $this->cachePool->save($item);
    }

    public function syncFull(PersonSyncResultInterface $res)
    {
        assert($res->isFullSyncResult());

        $this->logger->info('Starting a full sync');
        $schema = $this->translator->getSchema();

        $this->searchIndex->deleteOldCollections();
        $collectionName = $this->searchIndex->createNewCollection($schema);
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

        $this->saveCursor($collectionName, $res->getCursor());
    }

    public function syncPartial(string $collectionName, PersonSyncResultInterface $res)
    {
        assert(!$res->isFullSyncResult());

        foreach (array_chunk($res->getPersons(), self::CHUNK_SIZE) as $persons) {
            $documents = [];
            foreach ($persons as $person) {
                $documents[] = $this->personToDocument($person);
            }
            $this->searchIndex->addDocumentsToCollection($collectionName, $documents);

            // Also update the base data of all related DocumentFiles
            $relatedDocs = $this->getUpdatedRelatedDocumentFiles($collectionName, $documents);
            $this->searchIndex->addDocumentsToCollection($collectionName, $relatedDocs);
        }

        $this->saveCursor($collectionName, $res->getCursor());
    }

    public function sync(bool $full = false)
    {
        $this->searchIndex->ensureSetup();
        $collectionName = $this->searchIndex->getCollectionName();
        $metadata = $this->searchIndex->getSchemaMetadata($collectionName);
        if ($this->translator->isSchemaOutdated($metadata)) {
            $this->logger->info('Schema is outdated, falling back to a full sync');
            $full = true;
        }

        $cursor = $full ? null : $this->getCursor($collectionName);
        $this->logger->info('Starting a sync');
        $res = $this->personSync->getAllPersons($cursor);
        if ($res->isFullSyncResult()) {
            $this->logger->info('Starting a full sync');
            $this->syncFull($res);
        } else {
            $this->logger->info('Starting a partial sync');
            $this->syncPartial($collectionName, $res);
        }
    }

    public function getUpdatedRelatedDocumentFiles(string $collectionName, array $personDocuments): array
    {
        $updateDocuments = [];
        foreach ($personDocuments as $personDocument) {
            $base = $personDocument['base'];
            $id = $base['identNrObfuscated'];
            $relatedDocs = $this->searchIndex->findDocuments($collectionName, 'DocumentFile', 'base.identNrObfuscated', $id);
            foreach ($relatedDocs as &$relatedDoc) {
                $relatedDoc['base'] = $base;
            }
            $updateDocuments = array_merge($updateDocuments, $relatedDocs);
        }

        return $updateDocuments;
    }

    public function syncOne(string $id)
    {
        $this->logger->info('Syncing one person: '.$id);
        $collectionName = $this->searchIndex->getCollectionName();
        $cursor = $this->getCursor($collectionName);
        $res = $this->personSync->getPersons([$id], $cursor);
        $documents = [];
        foreach ($res->getPersons() as $person) {
            $documents[] = $this->personToDocument($person);
        }
        $this->searchIndex->addDocumentsToCollection($collectionName, $documents);

        // Also update the base data of all related DocumentFiles
        $relatedDocs = $this->getUpdatedRelatedDocumentFiles($collectionName, $documents);
        $this->searchIndex->addDocumentsToCollection($collectionName, $relatedDocs);

        $this->saveCursor($collectionName, $res->getCursor());
    }

    public function queueSync()
    {
        $lock = $this->lockFactory->createLockFromKey($this->getSyncLockKey(), 3600, false);
        if ($lock->acquire(false)) {

        } else {

        }
    }

    public function personToDocument(array $person): array
    {
        return $this->translator->translateDocument('person', $person);
    }

    public function fileDataToPartialDocument(array $fileData): array
    {
        $bucketId = $this->blobService->getBucketId();
        $metadata = json_decode($fileData['metadata'], associative: true, flags: JSON_THROW_ON_ERROR);
        $objectType = $metadata['objectType'];
        $input = [
            'id' => $fileData['identifier'],
            'fileSource' => $bucketId,
            'fileName' => $fileData['fileName'],
            'mimeType' => $fileData['mimeType'],
            'dateCreated' => $fileData['dateCreated'],
            'dateModified' => $fileData['dateModified'],
            'metadata' => $metadata,
        ];

        return $this->translator->translateDocument($objectType, $input);
    }
}
