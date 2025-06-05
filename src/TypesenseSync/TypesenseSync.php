<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\Blob\BlobService;
use Dbp\Relay\CabinetBundle\PersonSync\PersonSyncInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TypesenseSync implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private TypesenseClient $searchIndex;
    private PersonSyncInterface $personSync;
    private DocumentTransformer $transformer;
    private BlobService $blobService;

    // Chunk processing to reduce memory consumption
    private const CHUNK_SIZE = 10000;

    private const SHARED_FIELDS = ['person'];

    public function __construct(TypesenseClient $searchIndex, PersonSyncInterface $personSync, DocumentTransformer $transformer, BlobService $blobService)
    {
        $this->searchIndex = $searchIndex;
        $this->personSync = $personSync;
        $this->logger = new NullLogger();
        $this->transformer = $transformer;
        $this->blobService = $blobService;
    }

    public function getConnectionBaseUrl(): string
    {
        return $this->searchIndex->getConnectionBaseUrl();
    }

    private function getCursor(string $collectionName): ?string
    {
        $metadata = $this->searchIndex->getCollectionMetadata($collectionName);

        return $metadata['cabinet:syncCursor'] ?? null;
    }

    private function addDocumentsToCollection(string $collectionName, array $documents): void
    {
        foreach ($documents as &$document) {
            $document = $this->transformer->finalizeDocument($document);
        }

        $this->searchIndex->addDocumentsToCollection($collectionName, $documents);
    }

    /**
     * Sync all files from blob into typesense. Needs to be called after all persons have already been synced.
     */
    public function upsertAllFiles(string $collectionName): void
    {
        $this->logger->info('Syncing all blob files');
        $fileDataIterable = $this->blobService->getAllFiles();
        $this->upsertMultipleFileData($collectionName, $fileDataIterable);
        $this->searchIndex->clearSearchCache();
    }

    public function upsertFile(string $blobFileId): void
    {
        $fileData = $this->blobService->getFile($blobFileId);
        $collectionName = $this->searchIndex->getCollectionName();
        $this->upsertFileData($collectionName, $fileData);
        $this->searchIndex->clearSearchCache();
    }

    public function getAllPersonIds(string $collectionName): array
    {
        $personIdField = $this->transformer->getPersonIdField();

        return array_map('strval', array_keys($this->searchIndex->getBaseMapping($collectionName, 'Person', $personIdField, [$personIdField])));
    }

    public function upsertMultipleFileData(string $collectionName, iterable $fileDataList): void
    {
        $this->logger->info('Syncing all blob files');

        $this->logger->info('Fetch mapping for base data');
        $sharedFields = $this->transformer->getSharedFields();
        $personIdField = $this->transformer->getPersonIdField();
        // First we get a mapping of the base ID to the base content for all Persons in typesense
        $baseMapping = $this->searchIndex->getBaseMapping($collectionName, 'Person', $personIdField, $sharedFields);
        $this->logger->debug('Base entries found: '.count($baseMapping));

        // Then we fetch all files from the blob bucket, transform it to the typsensese schema, and enrich it
        // with the base data of the persons from the mapping above.
        // In case there is no corresponding person in typesense we simply drop the file atm.
        // In the end we upsert everything to typesense.
        $newDocuments = [];
        $notFound = [];
        $documentCount = 0;
        foreach ($fileDataList as $fileData) {
            foreach ($this->fileDataToPartialDocuments($fileData) as $transformed) {
                $id = Utils::getField($transformed, $personIdField);
                // XXX: If the related person isn't in typesense, we just ignore the file
                if (!array_key_exists($id, $baseMapping)) {
                    if (!array_key_exists($id, $notFound)) {
                        $this->logger->warning('For file '.$fileData['identifier'].' (and possibly more) with baseId "'.$id.'" no matching base data found, skipping');
                        $notFound[$id] = null;
                    }
                    continue;
                }
                $transformed = array_merge($transformed, $baseMapping[$id]);
                $newDocuments[] = $transformed;
                ++$documentCount;
                if (count($newDocuments) > self::CHUNK_SIZE) {
                    $this->addDocumentsToCollection($collectionName, $newDocuments);
                    $newDocuments = [];
                }
            }
        }
        $this->addDocumentsToCollection($collectionName, $newDocuments);
        $this->logger->info('Upserted '.$documentCount.' file documents into typesense');
        $this->searchIndex->clearSearchCache();
    }

    public function upsertFileData(string $collectionName, array $fileData): void
    {
        $sharedFields = $this->transformer->getSharedFields();
        $personIdField = $this->transformer->getPersonIdField();
        foreach ($this->fileDataToPartialDocuments($fileData) as $partialFileDocument) {
            $blobFilePersonId = Utils::getField($partialFileDocument, $personIdField);
            $results = $this->searchIndex->findDocuments($collectionName, 'Person', $personIdField, $blobFilePersonId);
            if ($results) {
                foreach ($sharedFields as $field) {
                    $partialFileDocument[$field] = $results[0][$field];
                }
            } else {
                // FIXME: what if the person is missing? Just ignore the document until the next full sync, or poll later?
                // atm the schema needs those fields, so just skip for now
                $this->logger->warning('No person found in typesense matching '.$blobFilePersonId.'. Skipping blob file');

                return;
            }
            $this->addDocumentsToCollection($collectionName, [$partialFileDocument]);
        }
        $this->searchIndex->clearSearchCache();
    }

    public function deleteFile(string $blobFileId): void
    {
        $documentIdField = $this->transformer->getDocumentIdField();
        $collectionName = $this->searchIndex->getCollectionName();
        $results = $this->searchIndex->findDocuments($collectionName, 'DocumentFile', $documentIdField, $blobFileId);
        foreach ($results as $result) {
            $typesenseId = $result['id'];
            $this->searchIndex->deleteDocument($collectionName, $typesenseId);
        }
        $this->searchIndex->clearSearchCache();
    }

    private function saveCursor(string $collectionName, ?string $cursor): void
    {
        $metadata = $this->searchIndex->getCollectionMetadata($collectionName);
        $metadata['cabinet:syncCursor'] = $cursor;
        $now = (new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')))->format(\DateTime::ATOM);
        $metadata['cabinet:updatedAt'] = $now;
        $this->searchIndex->setCollectionMetadata($collectionName, $metadata);
    }

    public function syncFull()
    {
        $this->logger->info('Starting a full sync');
        $schema = $this->transformer->getSchema();
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
            $this->addDocumentsToCollection($collectionName, $documents);
        }

        $this->upsertAllFiles($collectionName);

        $this->searchIndex->updateAlias($collectionName);
        $this->searchIndex->deleteOldCollections();

        $this->saveCursor($collectionName, $res->getCursor());
        $this->searchIndex->clearSearchCache();
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

            $metadata = $this->searchIndex->getCollectionMetadata($collectionName);
            $outdated = $this->transformer->isSchemaOutdated($metadata);
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
                $this->addDocumentsToCollection($collectionName, $documents);

                // Also update the base data of all related DocumentFiles
                $relatedDocs = $this->getUpdatedRelatedDocumentFiles($collectionName, $documents);
                $this->addDocumentsToCollection($collectionName, $relatedDocs);
            }

            $this->saveCursor($collectionName, $res->getCursor());
            $this->searchIndex->clearSearchCache();
        }
    }

    public function getUpdatedRelatedDocumentFiles(string $collectionName, array $personDocuments): array
    {
        $personIdField = $this->transformer->getPersonIdField();
        $updateDocuments = [];
        foreach ($personDocuments as $personDocument) {
            $id = Utils::getField($personDocument, $personIdField);
            $relatedDocs = $this->searchIndex->findDocuments($collectionName, 'DocumentFile', $personIdField, $id);
            foreach ($relatedDocs as &$relatedDoc) {
                foreach (self::SHARED_FIELDS as $field) {
                    $relatedDoc[$field] = $personDocument[$field];
                }
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
        if ($res->getPersons() === []) {
            throw new NotFoundHttpException('Unkown person: '.$id);
        }
        $documents = [];
        foreach ($res->getPersons() as $person) {
            foreach ($this->personToDocuments($person) as $document) {
                $documents[] = $document;
            }
        }
        $this->addDocumentsToCollection($collectionName, $documents);

        // Also update the base data of all related DocumentFiles
        $relatedDocs = $this->getUpdatedRelatedDocumentFiles($collectionName, $documents);
        $this->addDocumentsToCollection($collectionName, $relatedDocs);

        $this->saveCursor($collectionName, $res->getCursor());
        $this->searchIndex->clearSearchCache();
    }

    public function personToDocuments(array $person): array
    {
        return $this->transformer->transformDocument('person', $person);
    }

    public function fileDataToPartialDocuments(array $fileData): array
    {
        $bucketId = $this->blobService->getBucketIdentifier();
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
        if (isset($fileData['deleteAt'])) {
            $input['deleteAt'] = $fileData['deleteAt'];
        }

        return $this->transformer->transformDocument($objectType, $input);
    }
}
