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

        if ($cursor === null) {
            $this->logger->info('Starting a full sync');
            $schema = $this->translator->getSchema();

            $this->searchIndex->setSchema($schema);
            $this->searchIndex->ensureSetup();
            $collectionName = $this->searchIndex->createNewCollection();

            $res = $this->personSync->getAllPersons();
            $documents = [];
            foreach ($res->getPersons() as $person) {
                $documents[] = $this->personToDocument($person);
            }

            $this->searchIndex->addDocumentsToCollection($collectionName, $documents);
            if ($documents !== []) {
                $this->addDummyDocuments($collectionName, $documents);
            }
            $this->searchIndex->ensureSetup();

            $this->searchIndex->updateAlias($collectionName);
            $this->searchIndex->expireOldCollections();

            $this->saveCursor($res->getCursor());
        } else {
            $this->logger->info('Starting a partial sync');
            $res = $this->personSync->getAllPersons($cursor);

            $documents = [];
            foreach ($res->getPersons() as $person) {
                $documents[] = $this->personToDocument($person);
            }
            $collectionName = $this->searchIndex->getCollectionName();
            $this->searchIndex->addDocumentsToCollection($collectionName, $documents);

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
        $countryOfOrigin = ['Österreich', 'Deutschland', 'France', 'Italia', 'Schweiz'];
        $fileNames = self::generateRandomPDFNames();

        // citizenshipCertificate
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-citizenshipCertificate.$i",
                'objectType' => 'file-cabinet-citizenshipCertificate',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileName' => $getRandom($fileNames),
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
                        'additionalType' => 'CitizenshipCertificate',
                    ],
                    'citizenshipCertificate' => [
                        'countryOfOrigin' => $getRandom($countryOfOrigin),
                    ],
                ],
            ];
        }

        // identityDocument
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-identityDocument.$i",
                'objectType' => 'file-cabinet-identityDocument',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileName' => $getRandom($fileNames),
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
                        'additionalType' => $getRandom(['PersonalLicence', 'Passport', 'DriversLicence']),
                    ],
                    'identityDocument' => [
                        'countryOfOrigin' => $getRandom($countryOfOrigin),
                        'identifier' => $getRandom(['AT-L-123456', 'P7890123', '23456789']),
                        'dateCreated' => $getRandom(['2021-02-11 11:30', '2021-02-12 19:40']),
                    ],
                ],
            ];
        }

        // minimalSchema
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-minimalSchema.$i",
                'objectType' => 'file-cabinet-minimalSchema',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileName' => $getRandom($fileNames),
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
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
                'objectType' => 'file-cabinet-communication',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileName' => $getRandom($fileNames),
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
                        'additionalType' => $getRandom(['PhoneCall', 'InPersonCommunication']),
                    ],
                    'communication' => [
                        'abstract' => 'Short description or summarization of the phone call or in-person communication',
                        'agent' => [
                            'givenName' => 'James',
                            'familyName' => 'Bond',
                        ],
                        'dateCreated' => $getRandom(['2021-02-11 11:30', '2021-02-12 19:40']),
                    ],
                ],
            ];
        }

        // email
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-email.$i",
                'objectType' => 'file-cabinet-email',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileName' => $getRandom($fileNames),
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
                        'additionalType' => $getRandom(['Email']),
                    ],
                    'email' => [
                        'abstract' => 'Short description or summarization of the email. Can also be a plain text copy.',
                        'dateCreated' => $getRandom(['2021-02-11 11:30', '2021-02-12 19:40']),
                        'sender' => [
                            'givenName' => 'Elim',
                            'familyName' => 'Garak',
                            'email' => 'garak@ds9.org',
                        ],
                        'recipient' => [
                            'givenName' => 'Enabran',
                            'familyName' => 'Tain',
                            'email' => 'enabran@obsidian.org',
                        ],
                        'ccRecipient' => 'benjamin@ds9.org',
                        'bccRecipient' => 'odo@ds9.org',
                    ],
                ],
            ];
        }

        // letter
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-letter.$i",
                'objectType' => 'file-cabinet-letter',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileName' => $getRandom($fileNames),
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
                        'additionalType' => $getRandom(['PostalLetter']),
                    ],
                    'letter' => [
                        'abstract' => 'Short description or summarization of the email. Can also be a plain text copy.',
                        'dateSent' => $getRandom(['2021-02-11', '2021-02-12']),
                        'dateReceived' => $getRandom(['2021-02-13', '2021-02-14']),
                        'sender' => [
                            'givenName' => 'Elim',
                            'familyName' => 'Garak',
                            'worksFor' => [
                                'legalName' => 'Legal',
                                'department' => 'Department',
                            ],
                            'legalName' => 'Legal',
                            'department' => 'Department',
                        ],
                        'recipient' => [
                            'givenName' => 'Enabran',
                            'familyName' => 'Tain',
                            'worksFor' => [
                                'legalName' => 'Legal',
                                'department' => 'Department',
                            ],
                            'legalName' => 'Legal',
                            'department' => 'Department',
                        ],
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
