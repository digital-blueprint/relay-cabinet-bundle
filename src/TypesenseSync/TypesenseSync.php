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

    public function __construct(SearchIndex $searchIndex, PersonSyncInterface $personSync)
    {
        $this->cachePool = new ArrayAdapter();
        $this->searchIndex = $searchIndex;
        $this->personSync = $personSync;
        $this->logger = new NullLogger();
    }

    public function setCache(?CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;
    }

    public function sync(bool $full = false)
    {
        $item = $this->cachePool->getItem('cursor');
        $cursor = null;
        if ($item->isHit() && !$full) {
            $cursor = $item->get();
        }

        if ($cursor === null) {
            $this->logger->info('Starting a full sync');
            $schema = json_decode(file_get_contents(__DIR__.'/schema.json'), true, flags: JSON_THROW_ON_ERROR);

            $this->searchIndex->setSchema($schema);
            $this->searchIndex->ensureSetup();
            $collectionName = $this->searchIndex->createNewCollection();

            $res = $this->personSync->getAllPersons();
            $documents = [];
            foreach ($res->getPersons() as $person) {
                $documents[] = self::personToDocument($person);
            }

            $this->searchIndex->addDocumentsToCollection($collectionName, $documents);
            $this->addDummyDocuments($collectionName, $documents);
            $this->searchIndex->ensureSetup();

            $this->searchIndex->updateAlias($collectionName);
            $this->searchIndex->expireOldCollections();

            $item->set($res->getCursor());
            $this->cachePool->save($item);
        } else {
            $this->logger->info('Starting a partial sync');
            $res = $this->personSync->getAllPersons($cursor);

            $documents = [];
            foreach ($res->getPersons() as $person) {
                $documents[] = self::personToDocument($person);
            }
            $collectionName = $this->searchIndex->getCollectionName();
            $this->searchIndex->addDocumentsToCollection($collectionName, $documents);
            $this->addDummyDocuments($collectionName, $documents);

            $item->set($res->getCursor());
            $item->expiresAfter(3600 * 24);
            $this->cachePool->save($item);
        }
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

        // citizenshipCertificate
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-citizenshipCertificate.$i",
                'objectType' => 'file-cabinet-citizenshipCertificate',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
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

        // conversation
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-conversation.$i",
                'objectType' => 'file-cabinet-conversation',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'comment' => $getRandom($comment),
                        'studentLifeCyclePhase' => $getRandom($phases),
                        'subjectOf' => $getRandom($subjectOf),
                        'additionalType' => $getRandom(['PhoneCall', 'InPersonConversation']),
                    ],
                    'conversation' => [
                        'abstract' => 'Short description or summarization of the phone call or in-person conversation',
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

    public static function personToDocument(array $person): array
    {
        $studies = [];
        foreach ($person['studies'] as $study) {
            $studies[] = [
                'studyKey' => $study['key'],
                'studyType' => $study['type'],
                'studyName' => $study['name'],
                'studyCurriculumVersion' => $study['curriculumVersion'],
            ];
        }

        $applications = [];
        foreach ($person['applications'] as $application) {
            $applications[] = [
                'studyKey' => $application['studyKey'],
                'studyType' => $application['studyType'],
                'studyName' => $application['studyName'],
            ];
        }

        return [
            'id' => 'person.'.$person['id'],
            'objectType' => 'person',
            'base' => [
                'givenName' => $person['givenName'],
                'familyName' => $person['familyName'],
                'persName' => $person['givenName'].' '.$person['familyName'],
                'identNrObfuscated' => $person['id'],
            ],
            'person' => [
                'studies' => $studies,
                'applications' => $applications,
                'nationality' => [
                    'key' => $person['nationality']['key'],
                    'text' => $person['nationality']['translations']['de'],
                ],
                'gender' => [
                    'key' => $person['gender']['key'],
                    'text' => $person['gender']['translations']['de'],
                ],
                'studentStatus' => [
                    'key' => $person['studentStatus']['key'],
                    'text' => $person['studentStatus']['translations']['de'],
                ],
            ],
        ];
    }
}
