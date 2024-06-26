<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Command;

use Dbp\Relay\CabinetBundle\PersonSync\PersonSyncInterface;
use Dbp\Relay\CabinetBundle\Service\CabinetService;
use Dbp\Relay\CabinetBundle\TypesenseClient\SearchIndex;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    private CabinetService $cabinetService;
    private SearchIndex $searchIndex;
    private PersonSyncInterface $personSync;

    public function __construct(CabinetService $cabinetService, SearchIndex $searchIndex, PersonSyncInterface $personSync)
    {
        parent::__construct();

        $this->cabinetService = $cabinetService;
        $this->searchIndex = $searchIndex;
        $this->personSync = $personSync;
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:cabinet:sync');
        $this->setDescription('Sync command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schema = json_decode('{
  "name": "persons",
  "fields": [
    {
      "name": "objectType",
      "type": "string",
      "facet": false,
      "optional": false
    },
    {
      "name": "person:id",
      "type": "string",
      "facet": false,
      "optional": false
    },
    {
      "name": "person:givenName",
      "type": "string",
      "facet": false,
      "optional": false
    },
    {
      "name": "person:familyName",
      "type": "string",
      "facet": false,
      "optional": false
    }
  ]
}', true, flags: JSON_THROW_ON_ERROR);

        $this->searchIndex->setSchema($schema);
        $this->searchIndex->ensureSetup();
        $collectionName = $this->searchIndex->createNewCollection();

        $res = $this->personSync->getAllPersons();
        $documents = [];
        foreach ($res->getPersons() as $person) {
            $documents[] = [
                'objectType' => 'person',
                'person:id' => $person['id'],
                'person:givenName' => $person['givenName'],
                'person:familyName' => $person['familyName'],
            ];
        }

        $this->searchIndex->addDocumentsToCollection($collectionName, $documents);
        $this->searchIndex->updateAlias($collectionName);
        $this->searchIndex->expireOldCollections();

        return 0;
    }
}
