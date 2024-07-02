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

            $item->set($res->getCursor());
            $item->expiresAfter(3600 * 24);
            $this->cachePool->save($item);
        }
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
            'person' => [
                'identNrObfuscated' => $person['id'],
                'givenName' => $person['givenName'],
                'familyName' => $person['familyName'],
                'persName' => $person['givenName'].' '.$person['familyName'],
                'birthDate' => '1970-01-01',
                'birthYear' => 1970,
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
