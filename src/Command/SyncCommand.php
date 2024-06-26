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
        $output->writeln('todo');

        return 0;
    }
}
