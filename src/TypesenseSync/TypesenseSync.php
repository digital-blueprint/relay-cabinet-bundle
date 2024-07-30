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
                $documents[] = self::personToDocument($person);
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
            $documents[] = self::personToDocument($person);
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

        // conversation
        for ($i = 0; $i < 50; ++$i) {
            $documents[] = [
                'id' => "file-cabinet-conversation.$i",
                'objectType' => 'file-cabinet-conversation',
                'base' => $getRandom($personDocuments)['base'],
                'file' => [
                    'base' => [
                        'fileName' => $getRandom($fileNames),
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

    public static function personToDocument(array $input): array
    {
        // By removing the key from the input we can more easily track which values
        // are already translated and which aren't.
        $pop = function (&$array, $key) {
            $value = $array[$key];
            unset($array[$key]);

            return $value;
        };

        $studies = [];
        foreach ($input['studies'] as &$study) {
            $studyStatus = $pop($study, 'status');
            $studyResult = [
                'studyKey' => $pop($study, 'key'),
                'studyType' => $pop($study, 'type'),
                'studyName' => $pop($study, 'name'),
                'studyCurriculumVersion' => $pop($study, 'curriculumVersion'),
                'studyImmatriculationDate' => $pop($study, 'immatriculationDate'),
                'studyImmatriculationSemester' => $pop($study, 'immatriculationSemester'),
                'coUrl' => $pop($study, 'webUrl'),
                'studySemester' => $pop($study, 'semester'),
                'studyStatus' => [
                    'key' => $studyStatus['key'],
                    'text' => $studyStatus['translations']['de'],
                ],
            ];
            $exmatriculationDate = $pop($study, 'exmatriculationDate');
            if ($exmatriculationDate !== null) {
                $studyResult['studyExmatriculationDate'] = $exmatriculationDate;
            }
            $exmatriculationSemester = $pop($study, 'exmatriculationSemester');
            if ($exmatriculationSemester !== null) {
                $studyResult['studyExmatriculatioSemester'] = $exmatriculationSemester;
            }
            $qualificationType = $pop($study, 'qualificationType');
            if ($qualificationType !== null) {
                $studyResult['studyQualificationState'] = [
                    'key' => $qualificationType['key'],
                    'text' => $qualificationType['translations']['de'],
                ];
            }
            $qualificationDate = $pop($study, 'qualificationDate');
            if ($qualificationDate !== null) {
                $studyResult['studyQualificationDate'] = $qualificationDate;
            }
            $qualificationState = $pop($study, 'qualificationState');
            if ($qualificationState !== null) {
                $studyResult['studyQualificationState'] = [
                    'key' => $qualificationState['key'],
                    'text' => $qualificationState['translations']['de'],
                ];
            }
            $exmatriculationType = $pop($study, 'exmatriculationType');
            if ($exmatriculationType !== null) {
                $studyResult['studyExmatriculationType'] = [
                    'key' => $exmatriculationType['key'],
                    'text' => $exmatriculationType['translations']['de'],
                ];
            }
            $studies[] = $studyResult;
        }

        $applications = [];
        foreach ($input['applications'] as &$application) {
            $applications[] = [
                'studyKey' => $pop($application, 'studyKey'),
                'studyType' => $pop($application, 'studyType'),
                'studyName' => $pop($application, 'studyName'),
            ];
        }

        $nationality = $pop($input, 'nationality');
        $gender = $pop($input, 'gender');
        $studentStatus = $pop($input, 'studentStatus');
        $personalStatus = $pop($input, 'personalStatus');
        $admissionQualificationType = $pop($input, 'admissionQualificationType');

        $person = [
            'studies' => $studies,
            'applications' => $applications,
            'coUrl' => $pop($input, 'webUrl'),
            'syncTimestamp' => (new \DateTimeImmutable($pop($input, 'syncDateTime')))->getTimestamp(),
            'nationality' => [
                'key' => $nationality['key'],
                'text' => $nationality['translations']['de'],
            ],
            'gender' => [
                'key' => $gender['key'],
                'text' => $gender['translations']['de'],
            ],
            'studentStatus' => [
                'key' => $studentStatus['key'],
                'text' => $studentStatus['translations']['de'],
            ],
            'admissionQualificationType' => [
                'key' => $admissionQualificationType['key'],
                'text' => $admissionQualificationType['translations']['de'],
            ],
            'schoolCertificateDate' => $pop($input, 'schoolCertificateDate'),
            'personalStatus' => [
                'key' => $personalStatus['key'],
                'text' => $personalStatus['translations']['de'],
            ],
            'immatriculationDate' => $pop($input, 'immatriculationDate'),
            'immatriculationSemester' => $pop($input, 'immatriculationSemester'),
        ];

        $exmatriculationStatus = $pop($input, 'exmatriculationStatus');
        if ($exmatriculationStatus !== null) {
            $person['exmatriculationStatus'] = [
                'key' => $exmatriculationStatus['key'],
                'text' => $exmatriculationStatus['translations']['de'],
            ];
        }

        $exmatriculationDate = $pop($input, 'exmatriculationDate');
        if ($exmatriculationDate !== null) {
            $person['exmatriculationDate'] = $exmatriculationDate;
        }

        $formerFamilyName = $pop($input, 'formerFamilyName');
        if ($formerFamilyName !== null) {
            $person['formerFamilyName'] = $formerFamilyName;
        }

        $academicTitlePreceding = $pop($input, 'academicTitlePreceding');
        if ($academicTitlePreceding !== null) {
            $person['academicTitlePreceding'] = $academicTitlePreceding;
        }

        $academicTitleFollowing = $pop($input, 'academicTitleFollowing');
        if ($academicTitleFollowing !== null) {
            $person['academicTitleFollowing'] = $academicTitleFollowing;
        }

        $socialSecurityNr = $pop($input, 'socialSecurityNumber');
        if ($socialSecurityNr !== null) {
            $person['socialSecurityNr'] = $socialSecurityNr;
        }

        $sectorSpecificPersonalIdentifier = $pop($input, 'sectorSpecificPersonalIdentifier');
        if ($sectorSpecificPersonalIdentifier !== null) {
            $person['bpk'] = $sectorSpecificPersonalIdentifier;
        }

        $admissionQualificationState = $pop($input, 'admissionQualificationState');
        if ($admissionQualificationState !== null) {
            $person['admissionQualificationState'] = [
                'key' => $admissionQualificationState['key'],
                'text' => $admissionQualificationState['translations']['de'],
            ];
        }

        $homeAddressNote = $pop($input, 'homeAddressNote');
        if ($homeAddressNote !== null) {
            $person['homeAddress']['note'] = $homeAddressNote;
        }
        $homeAddressStreet = $pop($input, 'homeAddressStreet');
        if ($homeAddressStreet !== null) {
            $person['homeAddress']['street'] = $homeAddressStreet;
        }
        $homeAddressPlace = $pop($input, 'homeAddressPlace');
        if ($homeAddressPlace !== null) {
            $person['homeAddress']['place'] = $homeAddressPlace;
        }
        $homeAddressPostCode = $pop($input, 'homeAddressPostCode');
        if ($homeAddressPostCode !== null) {
            $person['homeAddress']['postCode'] = $homeAddressPostCode;
        }
        $homeAddressCountry = $pop($input, 'homeAddressCountry');
        if ($homeAddressCountry !== null) {
            $person['homeAddress']['country'] = [
                'key' => $homeAddressCountry['key'],
                'text' => $homeAddressCountry['translations']['de'],
            ];
        }

        $studyAddressNote = $pop($input, 'studyAddressNote');
        if ($studyAddressNote !== null) {
            $person['studAddress']['note'] = $studyAddressNote;
        }
        $studyAddressStreet = $pop($input, 'studyAddressStreet');
        if ($studyAddressStreet !== null) {
            $person['studAddress']['street'] = $studyAddressStreet;
        }
        $studyAddressPlace = $pop($input, 'studyAddressPlace');
        if ($studyAddressPlace !== null) {
            $person['studAddress']['place'] = $studyAddressPlace;
        }
        $studyAddressPostCode = $pop($input, 'studyAddressPostCode');
        if ($studyAddressPostCode !== null) {
            $person['studAddress']['postCode'] = $studyAddressPostCode;
        }
        $studyAddressCountry = $pop($input, 'studyAddressCountry');
        if ($studyAddressCountry !== null) {
            $person['studAddress']['country'] = [
                'key' => $studyAddressCountry['key'],
                'text' => $studyAddressCountry['translations']['de'],
            ];
        }

        $emailAddressUniversity = $pop($input, 'emailAddressUniversity');
        if ($emailAddressUniversity !== null) {
            $person['emailAddressUniversity'] = $emailAddressUniversity;
        }
        $emailAddressConfirmed = $pop($input, 'emailAddressConfirmed');
        if ($emailAddressConfirmed !== null) {
            $person['emailAddressConfirmed'] = $emailAddressConfirmed;
        }
        $emailAddressTemporary = $pop($input, 'emailAddressTemporary');
        if ($emailAddressTemporary !== null) {
            $person['emailAddressTemporary'] = $emailAddressTemporary;
        }

        $nationalitySecondary = $pop($input, 'nationalitySecondary');
        if ($nationalitySecondary !== null) {
            $person['nationalitySecondary'] = [
                'key' => $nationalitySecondary['key'],
                'text' => $nationalitySecondary['translations']['de'],
            ];
        }

        $givenName = $pop($input, 'givenName');
        $familyName = $pop($input, 'familyName');
        $id = $pop($input, 'id');
        $birthDate = $pop($input, 'birthDate');
        $birthYear = (int) substr($birthDate, 0, 4);
        $result = [
            'id' => 'person.'.$id,
            'objectType' => 'person',
            'base' => [
                'givenName' => $givenName,
                'familyName' => $familyName,
                'persName' => $givenName.' '.$familyName,
                'identNrObfuscated' => $id,
                'birthDate' => $birthDate,
                'birthYear' => $birthYear,
                'studId' => $pop($input, 'studentId'),
                'stPersonNr' => $pop($input, 'studentPersonNumber'),
            ],
            'person' => $person,
        ];

        return $result;
    }
}
