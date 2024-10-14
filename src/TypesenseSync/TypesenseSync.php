<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\Blob\BlobService;
use Dbp\Relay\CabinetBundle\PersonSync\PersonSyncInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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

    public function __construct(TypesenseClient $searchIndex, PersonSyncInterface $personSync, DocumentTranslator $translator, BlobService $blobService)
    {
        $this->cachePool = new ArrayAdapter();
        $this->searchIndex = $searchIndex;
        $this->personSync = $personSync;
        $this->logger = new NullLogger();
        $this->translator = $translator;
        $this->blobService = $blobService;
    }

    public function getConnectionBaseUrl(): string
    {
        return $this->searchIndex->getConnectionBaseUrl();
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
        $baseMapping = $this->searchIndex->getBaseMapping($collectionName, 'Person', 'identNrObfuscated', 'person');
        $this->logger->debug('Base entries found: '.count($baseMapping));

        // Then we fetch all files from the blob bucket, translate it to the typsensese schema, and enrich it
        // with the base data of the persons from the mapping above.
        // In case there is no corresponding person in typesense we simply drop the file atm.
        // In the end we upsert everything to typesense.
        $newDocuments = [];
        $notFound = [];
        $documentCount = 0;
        foreach ($this->blobService->getAllFiles() as $fileData) {
            foreach ($this->fileDataToPartialDocuments($fileData) as $translated) {
                $id = $translated['person']['identNrObfuscated'];
                // XXX: If the related person isn't in typesense, we just ignore the file
                if (!array_key_exists($id, $baseMapping)) {
                    if (!array_key_exists($id, $notFound)) {
                        $this->logger->warning('For file '.$fileData['identifier'].' (and possibly more) with baseId "'.$id.'" no matching base data found, skipping');
                        $notFound[$id] = null;
                    }
                    continue;
                }
                $translated['person'] = $baseMapping[$id];
                $newDocuments[] = $translated;
                ++$documentCount;
                if (count($newDocuments) > self::CHUNK_SIZE) {
                    $this->searchIndex->addDocumentsToCollection($collectionName, $newDocuments);
                    $newDocuments = [];
                }
            }
        }
        $this->searchIndex->addDocumentsToCollection($collectionName, $newDocuments);
        $this->logger->info('Upserted '.$documentCount.' file documents into typesense');
    }

    public function upsertFile(string $blobFileId): void
    {
        $collectionName = $this->searchIndex->getCollectionName();
        $fileData = $this->blobService->getFile($blobFileId);
        foreach ($this->fileDataToPartialDocuments($fileData) as $partialFileDocument) {
            $blobFileId = $partialFileDocument['person']['identNrObfuscated'];
            $results = $this->searchIndex->findDocuments($collectionName, 'Person', 'person.identNrObfuscated', $blobFileId);
            if ($results) {
                $partialFileDocument['person'] = $results[0]['person'];
            } else {
                // FIXME: what if the person is missing? Just ignore the document until the next full sync, or poll later?
                // atm the schema needs those fields, so just skip for now
                $this->logger->warning('No person found in typesense matching '.$blobFileId.'. Skipping blob file');

                return;
            }
            $this->searchIndex->addDocumentsToCollection($collectionName, [$partialFileDocument]);
        }
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

    public function syncFull()
    {
        $this->logger->info('Starting a full sync');
        $schema = $this->translator->getSchema();
        $this->searchIndex->deleteOldCollections();
        $collectionName = $this->searchIndex->createNewCollection($schema);

        $res = $this->personSync->getAllPersons();
        foreach (array_chunk($res->getPersons(), self::CHUNK_SIZE) as $persons) {
            $documents = [];
            foreach ($persons as $person) {
                foreach ($this->personToDocuments($person) as $document) {
                    $documents[] = $document;
                }
            }
            $this->searchIndex->addDocumentsToCollection($collectionName, $documents);
        }

        $this->upsertAllFiles($collectionName);

        $this->searchIndex->updateAlias($collectionName);
        $this->searchIndex->deleteOldCollections();

        $this->saveCursor($collectionName, $res->getCursor());
    }

    public function sync(bool $full = false)
    {
        $this->searchIndex->ensureSetup();
        $collectionName = $this->searchIndex->getCollectionName();
        $cursor = $this->getCursor($collectionName);

        if ($full || $cursor === null) {
            $this->syncFull();
        } else {
            $this->logger->info('Starting a partial sync');

            $metadata = $this->searchIndex->getSchemaMetadata($collectionName);
            $outdated = $this->translator->isSchemaOutdated($metadata);
            if ($outdated) {
                $this->logger->info('Schema is outdated, falling back to a full sync');
                $this->syncFull();

                return;
            }

            $res = $this->personSync->getAllPersons($cursor);
            foreach (array_chunk($res->getPersons(), self::CHUNK_SIZE) as $persons) {
                $documents = [];
                foreach ($persons as $person) {
                    foreach ($this->personToDocuments($person) as $document) {
                        $documents[] = $document;
                    }
                }
                $this->searchIndex->addDocumentsToCollection($collectionName, $documents);

                // Also update the base data of all related DocumentFiles
                $relatedDocs = $this->getUpdatedRelatedDocumentFiles($collectionName, $documents);
                $this->searchIndex->addDocumentsToCollection($collectionName, $relatedDocs);
            }

            $this->saveCursor($collectionName, $res->getCursor());
        }
    }

    public function getUpdatedRelatedDocumentFiles(string $collectionName, array $personDocuments): array
    {
        $updateDocuments = [];
        foreach ($personDocuments as $personDocument) {
            $base = $personDocument['person'];
            $id = $base['identNrObfuscated'];
            $relatedDocs = $this->searchIndex->findDocuments($collectionName, 'DocumentFile', 'person.identNrObfuscated', $id);
            foreach ($relatedDocs as &$relatedDoc) {
                $relatedDoc['person'] = $base;
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
            foreach ($this->personToDocuments($person) as $document) {
                $documents[] = $document;
            }
        }
        $this->searchIndex->addDocumentsToCollection($collectionName, $documents);

        // Also update the base data of all related DocumentFiles
        $relatedDocs = $this->getUpdatedRelatedDocumentFiles($collectionName, $documents);
        $this->searchIndex->addDocumentsToCollection($collectionName, $relatedDocs);

        $this->saveCursor($collectionName, $res->getCursor());
    }

    public function personToDocuments(array $person): array
    {
        return $this->translator->translateDocument('person', $person);
    }

    public function fileDataToPartialDocuments(array $fileData): array
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
