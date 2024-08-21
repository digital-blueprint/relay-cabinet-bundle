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
use Symfony\Component\Uid\Uuid;

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
                if ($documents !== []) {
                    $this->addDummyDocuments($collectionName, $documents);
                }
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

    private static function generateRandomPDFNames($count = 50): array
    {
        $fileNames = [];

        $words = [
            'report', 'analysis', 'summary', 'proposal', 'plan',
            'review', 'guide', 'manual', 'study', 'brief',
            'outline', 'overview', 'document', 'paper', 'thesis',
            'essay', 'article', 'journal', 'presentation', 'notes',
        ];

        $adjectives = [
            'annual', 'quarterly', 'monthly', 'weekly', 'daily',
            'final', 'interim', 'preliminary', 'revised', 'updated',
            'comprehensive', 'detailed', 'executive', 'technical', 'financial',
            'strategic', 'operational', 'marketing', 'sales', 'research',
        ];

        for ($i = 0; $i < $count; ++$i) {
            $adjective = $adjectives[array_rand($adjectives)];
            $word = $words[array_rand($words)];
            $number = rand(1, 99);
            $fileNames[] = $adjective.'_'.$word.'_'.$number.'.pdf';
        }

        return $fileNames;
    }

    public function addDummyDocuments(string $collectionName, array $personDocuments): void
    {
        $documents = [];

        $getRandom = function (array $values) {
            return $values[array_rand($values)];
        };

        $phases = ['Application phase', 'Study phase', 'Graduation phase', 'General documents'];
        $comment = ['Some comment', 'Some other comment'];
        $subjectOf = ['GZ 2021-0.123.456', 'AZ 10 C 1234/23', 'VR 2023/789-B', '567/2022-XYZ', '987654-AB/2023'];
        $studyField = ['234', '456', '890'];
        $fileSource = 'blob-cabinetBucket';
        $atType = 'DocumentFile';
        $fileNames = self::generateRandomPDFNames();

        // admissionNotice
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-admissionNotice.$i",
                '@type' => $atType,
                'objectType' => 'file-cabinet-admissionNotice',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileSource' => $fileSource,
                        'groupId' => Uuid::v7()->toRfc4122(),
                        'fileId' => Uuid::v7()->toRfc4122(),
                        'fileName' => $getRandom($fileNames),
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
                        'studyField' => $getRandom($studyField),
                        'additionalType' => 'AdmissionNotice',
                    ],
                    'admissionNotice' => [
                        'dateCreated' => $getRandom(['2024-12-24', '1970-01-01', '1978-01-03']),
                        'previousStudy' => $getRandom(['Something', 'Completely', 'Different']),
                        'decision' => $getRandom(['rejected', 'refused', 'granted']),
                    ],
                ],
            ];
        }

        // citizenshipCertificate
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-citizenshipCertificate.$i",
                '@type' => $atType,
                'objectType' => 'file-cabinet-citizenshipCertificate',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileSource' => $fileSource,
                        'groupId' => Uuid::v7()->toRfc4122(),
                        'fileId' => Uuid::v7()->toRfc4122(),
                        'fileName' => $getRandom($fileNames),
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
                        'studyField' => $getRandom($studyField),
                        'additionalType' => 'CitizenshipCertificate',
                    ],
                    'citizenshipCertificate' => [
                        'nationality' => $getRandom(['MNE', 'AUT', 'HRV']),
                        'dateCreated' => $getRandom(['2024-11-24', '1970-01-02', '1978-01-03']),
                    ],
                ],
            ];
        }

        // identityDocument
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-identityDocument.$i",
                '@type' => $atType,
                'objectType' => 'file-cabinet-identityDocument',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileSource' => $fileSource,
                        'groupId' => Uuid::v7()->toRfc4122(),
                        'fileId' => Uuid::v7()->toRfc4122(),
                        'fileName' => $getRandom($fileNames),
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
                        'studyField' => $getRandom($studyField),
                        'additionalType' => $getRandom(['PersonalLicence', 'Passport', 'DriversLicence']),
                    ],
                    'identityDocument' => [
                        'nationality' => $getRandom(['MNE', 'AUT', 'HRV']),
                        'identifier' => $getRandom(['AT-L-123456', 'P7890123', '23456789']),
                        'dateCreated' => $getRandom(['2024-11-26', '1970-01-03', '1978-01-03']),
                    ],
                ],
            ];
        }

        // minimalSchema
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-minimalSchema.$i",
                '@type' => $atType,
                'objectType' => 'file-cabinet-minimalSchema',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileSource' => $fileSource,
                        'groupId' => Uuid::v7()->toRfc4122(),
                        'fileId' => Uuid::v7()->toRfc4122(),
                        'fileName' => $getRandom($fileNames),
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
                        'studyField' => $getRandom($studyField),
                        'additionalType' => $getRandom(['BirthCertificate', 'MaritalStatusCertificate', 'SupervisionAcceptance']),
                    ],
                    'minimalSchema' => [
                    ],
                ],
            ];
        }

        // communication
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-communication.$i",
                '@type' => $atType,
                'objectType' => 'file-cabinet-communication',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileSource' => $fileSource,
                        'groupId' => Uuid::v7()->toRfc4122(),
                        'fileId' => Uuid::v7()->toRfc4122(),
                        'fileName' => $getRandom($fileNames),
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
                        'studyField' => $getRandom($studyField),
                        'additionalType' => $getRandom(['PhoneCall', 'InPersonCommunication']),
                    ],
                    'communication' => [
                        'abstract' => 'Short description or summarization of the phone call or in-person communication',
                        'agent' => [
                            'givenName' => 'James',
                            'familyName' => 'Bond',
                        ],
                        'dateCreated' => $getRandom(['2023-05-15T09:30:45+05:00', '2021-12-31T23:59:59+02:00', '2024-02-29T00:00:00+00:00']),
                    ],
                ],
            ];
        }

        $this->searchIndex->addDocumentsToCollection($collectionName, $documents);
    }

    public function personToDocument(array $person): array
    {
        return $this->translator->translateDocument('person', $person);
    }
}
