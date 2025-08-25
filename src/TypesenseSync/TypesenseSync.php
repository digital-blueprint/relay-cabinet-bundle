<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\BlobLibrary\Api\BlobFile;
use Dbp\Relay\CabinetBundle\Blob\BlobService;
use Dbp\Relay\CabinetBundle\PersonSync\PersonSyncInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

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

    private CollectionManager $collectionManager;

    public function __construct(TypesenseClient $searchIndex, PersonSyncInterface $personSync, DocumentTransformer $transformer, BlobService $blobService, CollectionManager $collectionManager, private MessageBusInterface $messageBus)
    {
        $this->searchIndex = $searchIndex;
        $this->personSync = $personSync;
        $this->logger = new NullLogger();
        $this->transformer = $transformer;
        $this->blobService = $blobService;
        $this->collectionManager = $collectionManager;
    }

    public function getConnectionBaseUrl(): string
    {
        return $this->searchIndex->getConnectionBaseUrl();
    }

    private function addDocuments(string $primaryCollectionName, array $documents): void
    {
        $groups = [];
        foreach ($documents as &$document) {
            $document = $this->transformer->finalizeDocument($document);
            $name = $this->collectionManager->getCollectionNameForDocument($primaryCollectionName, $document);
            $groups[$name][] = $document;
        }

        foreach ($groups as $collectionName => $docs) {
            $this->searchIndex->addDocumentsToCollection($collectionName, $docs);
        }
    }

    public function getLastFullSyncDate(): ?\DateTimeInterface
    {
        $primaryCollectionName = $this->collectionManager->getPrimaryCollectionName();

        return $this->collectionManager->getCreatedAt($primaryCollectionName);
    }

    public function getLastSyncDate(): ?\DateTimeInterface
    {
        $primaryCollectionName = $this->collectionManager->getPrimaryCollectionName();

        return $this->collectionManager->getUpdatedAt($primaryCollectionName);
    }

    /**
     * Sync all files from blob into typesense. Needs to be called after all persons have already been synced.
     */
    private function upsertAllFiles(string $primaryCollectionName): void
    {
        $this->logger->info('Syncing all blob files');
        $blobFileIterable = $this->blobService->getAllFiles();
        $this->upsertMultipleBlobFiles($primaryCollectionName, $blobFileIterable);
        $this->searchIndex->clearSearchCache();
    }

    public function upsertFile(string $blobFileId): void
    {
        $blobFile = $this->blobService->getFile($blobFileId);
        $primaryCollectionName = $this->collectionManager->getPrimaryCollectionName();
        $this->upsertBlobFile($primaryCollectionName, $blobFile);
        $this->searchIndex->clearSearchCache();
    }

    public function getAllPersonIds(string $primaryCollectionName): array
    {
        $personIdField = $this->transformer->getPersonIdField();
        $ids = [];
        foreach ($this->collectionManager->getAllCollectionNames($primaryCollectionName) as $collectionName) {
            $ids = array_merge($ids, array_map('strval', array_keys($this->searchIndex->getBaseMapping($collectionName, 'Person', $personIdField, [$personIdField]))));
        }

        return $ids;
    }

    private function getSharedFieldData(string $primaryCollectionName)
    {
        $sharedFields = $this->transformer->getSharedFields();
        $personIdField = $this->transformer->getPersonIdField();
        $baseMapping = [];
        foreach ($this->collectionManager->getAllCollectionNames($primaryCollectionName) as $collectionName) {
            $baseMapping = array_replace($baseMapping, $this->searchIndex->getBaseMapping($collectionName, 'Person', $personIdField, $sharedFields));
        }

        return $baseMapping;
    }

    /**
     * @param iterable<BlobFile> $blobFiles
     */
    public function upsertMultipleBlobFiles(string $primaryCollectionName, iterable $blobFiles): void
    {
        $this->logger->info('Syncing all blob files');

        $this->logger->info('Fetch mapping for base data');
        $personIdField = $this->transformer->getPersonIdField();
        // First we get a mapping of the base ID to the base content for all Persons in typesense
        $sharedFieldData = $this->getSharedFieldData($primaryCollectionName);
        $this->logger->debug('Base entries found: '.count($sharedFieldData));

        // Then we fetch all files from the blob bucket, transform it to the typsensese schema, and enrich it
        // with the base data of the persons from the mapping above.
        // In case there is no corresponding person in typesense we simply drop the file atm.
        // In the end we upsert everything to typesense.
        $newDocuments = [];
        $notFound = [];
        $documentCount = 0;
        foreach ($blobFiles as $blobFile) {
            foreach ($this->blobFileToPartialDocuments($blobFile) as $transformed) {
                $id = Utils::getField($transformed, $personIdField);
                // XXX: If the related person isn't in typesense, we just ignore the file
                if (!isset($sharedFieldData[$id])) {
                    if (!isset($notFound[$id])) {
                        $this->logger->warning('For file '.$blobFile->getIdentifier().' (and possibly more) with baseId "'.$id.'" no matching base data found, skipping');
                        $notFound[$id] = true;
                    }
                    continue;
                }
                $transformed = array_merge($transformed, $sharedFieldData[$id]);
                $newDocuments[] = $transformed;
                ++$documentCount;
                if (count($newDocuments) > self::CHUNK_SIZE) {
                    $this->addDocuments($primaryCollectionName, $newDocuments);
                    $newDocuments = [];
                }
            }
        }
        $this->addDocuments($primaryCollectionName, $newDocuments);
        $this->logger->info('Upserted '.$documentCount.' file documents into typesense');
        $this->searchIndex->clearSearchCache();
    }

    private function upsertBlobFile(string $primaryCollectionName, BlobFile $blobFile): void
    {
        $sharedFields = $this->transformer->getSharedFields();
        $personIdField = $this->transformer->getPersonIdField();
        foreach ($this->blobFileToPartialDocuments($blobFile) as $partialFileDocument) {
            $blobFilePersonId = Utils::getField($partialFileDocument, $personIdField);
            $collectionName = $this->collectionManager->getCollectionNameForDocument($primaryCollectionName, $partialFileDocument);
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
            $this->addDocuments($primaryCollectionName, [$partialFileDocument]);
        }
        $this->searchIndex->clearSearchCache();
    }

    public function deleteFile(string $blobFileId): void
    {
        $documentIdField = $this->transformer->getDocumentIdField();
        $primaryCollectionName = $this->collectionManager->getPrimaryCollectionName();
        foreach ($this->collectionManager->getAllCollectionNames($primaryCollectionName) as $collectionName) {
            $results = $this->searchIndex->findDocuments($collectionName, 'DocumentFile', $documentIdField, $blobFileId);
            foreach ($results as $result) {
                $typesenseId = $result['id'];
                $this->searchIndex->deleteDocument($collectionName, $typesenseId);
            }
        }
        $this->searchIndex->clearSearchCache();
    }

    /**
     * Puts typesense in a state where the API at least works. i.e. the schema, the alias and the collection are there.
     */
    public function ensureSetup(): void
    {
        $this->logger->info('Running setup');

        $needsSetup = false;
        foreach ($this->collectionManager->getAllAliases() as $alias) {
            if ($this->searchIndex->needsSetup($alias)) {
                $needsSetup = true;
                break;
            }
        }

        if ($needsSetup) {
            $this->logger->info('No alias or collection found, re-creating empty collection and alias');
            $schema = $this->transformer->getSchema();
            $primaryCollectionName = $this->collectionManager->createNewCollections($schema);
            $this->collectionManager->updateAliases($primaryCollectionName);
        }
        $this->searchIndex->updateProxyApiKeys($this->collectionManager->getAllAliases());
        $this->collectionManager->deleteOldCollections();
    }

    public function syncAsync(bool $forceFull = false, ?string $personId = null): void
    {
        $this->logger->debug('Creating new sync task');
        $task = new SyncTask($forceFull, $personId);
        $this->messageBus->dispatch($task);
    }

    public function sync(bool $forceFull = false)
    {
        $this->ensureSetup();

        $primaryCollectionName = $this->collectionManager->getPrimaryCollectionName();
        $cursor = $this->collectionManager->getCursor($primaryCollectionName);

        if ($forceFull || $cursor === null) {
            $res = $this->personSync->getAllPersons();
        } else {
            $metadata = $this->searchIndex->getCollectionMetadata($primaryCollectionName);
            $outdated = $this->transformer->isSchemaOutdated($metadata);
            if ($outdated) {
                $this->logger->info('Schema is outdated, falling back to forcing a full sync');

                $res = $this->personSync->getAllPersons();
            } else {
                $res = $this->personSync->getAllPersons($cursor);
            }
        }

        if ($res->isFullSyncResult()) {
            $this->logger->info('Starting a full sync');

            $schema = $this->transformer->getSchema();
            $primaryCollectionName = $this->collectionManager->createNewCollections($schema);

            foreach (array_chunk($res->getPersons(), self::CHUNK_SIZE) as $persons) {
                $documents = [];
                foreach ($persons as $person) {
                    foreach ($this->personToDocuments($person) as $document) {
                        $documents[] = $document;
                    }
                }
                $this->addDocuments($primaryCollectionName, $documents);
            }

            $this->upsertAllFiles($primaryCollectionName);
            $this->collectionManager->updateAliases($primaryCollectionName);
            $this->collectionManager->deleteOldCollections();

            $this->collectionManager->saveCursor($primaryCollectionName, $res->getCursor());
            $this->searchIndex->clearSearchCache();
        } else {
            $this->logger->info('Starting a partial sync');

            $primaryCollectionName = $this->collectionManager->getPrimaryCollectionName();
            foreach (array_chunk($res->getPersons(), self::CHUNK_SIZE) as $persons) {
                $documents = [];
                foreach ($persons as $person) {
                    foreach ($this->personToDocuments($person) as $document) {
                        $documents[] = $document;
                    }
                }
                $this->addDocuments($primaryCollectionName, $documents);

                // Also update the base data of all related DocumentFiles
                $relatedDocs = $this->getUpdatedRelatedDocumentFiles($primaryCollectionName, $documents);
                $this->addDocuments($primaryCollectionName, $relatedDocs);
            }

            $this->collectionManager->saveCursor($primaryCollectionName, $res->getCursor());
            $this->searchIndex->clearSearchCache();
        }
    }

    private function getUpdatedRelatedDocumentFiles(string $primaryCollectionName, array $personDocuments): array
    {
        $personIdField = $this->transformer->getPersonIdField();
        $updateDocuments = [];
        foreach ($personDocuments as $personDocument) {
            $id = Utils::getField($personDocument, $personIdField);
            $collectionName = $this->collectionManager->getCollectionNameForDocument($primaryCollectionName, $personDocument);
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

    public function getPersonIdForDocumentId(string $typesenseDocumentId): ?string
    {
        // Find the document, extract the person ID
        $primaryCollectionName = $this->collectionManager->getPrimaryCollectionName();
        foreach ($this->collectionManager->getAllCollectionNames($primaryCollectionName) as $collectionName) {
            $document = $this->searchIndex->getDocument($collectionName, $typesenseDocumentId);
            if ($document !== null) {
                $personIdField = $this->transformer->getPersonIdField();

                return Utils::getField($document, $personIdField);
            }
        }

        return null;
    }

    public function syncOne(string $personId)
    {
        $this->logger->info('Syncing one person: '.$personId);
        $primaryCollectionName = $this->collectionManager->getPrimaryCollectionName();
        $cursor = $this->collectionManager->getCursor($primaryCollectionName);
        $res = $this->personSync->getPersons([$personId], $cursor);
        if ($res->getPersons() === []) {
            throw new NotFoundHttpException('Unkown person: '.$personId);
        }
        $documents = [];
        foreach ($res->getPersons() as $person) {
            foreach ($this->personToDocuments($person) as $document) {
                $documents[] = $document;
            }
        }
        $this->addDocuments($primaryCollectionName, $documents);

        // Also update the base data of all related DocumentFiles
        $relatedDocs = $this->getUpdatedRelatedDocumentFiles($primaryCollectionName, $documents);
        $this->addDocuments($primaryCollectionName, $relatedDocs);

        $this->collectionManager->saveCursor($primaryCollectionName, $res->getCursor());
        $this->searchIndex->clearSearchCache();
    }

    private function personToDocuments(array $person): array
    {
        return $this->transformer->transformDocument('person', $person);
    }

    private function blobFileToPartialDocuments(BlobFile $blobFile): array
    {
        $bucketId = $this->blobService->getBucketIdentifier();
        $metadataJson = $blobFile->getMetadata();
        $metadata = $metadataJson !== null ? json_decode($metadataJson, associative: true, flags: JSON_THROW_ON_ERROR) : [];
        $objectType = $metadata['objectType'];

        $input = [
            'id' => $blobFile->getIdentifier(),
            'fileSource' => $bucketId,
            'fileName' => $blobFile->getFileName(),
            'mimeType' => $blobFile->getMimeType(),
            'dateCreated' => $blobFile->getDateCreated(),
            'dateModified' => $blobFile->getDateModified(),
            'deleteAt' => $blobFile->getDeleteAt(),
            'metadata' => $metadata,
        ];

        return $this->transformer->transformDocument($objectType, $input);
    }

    #[AsMessageHandler]
    public function handleSyncTask(SyncTask $task): void
    {
        $this->logger->debug('Handling sync task');
        if ($task->personId !== null) {
            $this->syncOne($task->personId);
        } else {
            $this->sync($task->forceFull);
        }
    }
}
