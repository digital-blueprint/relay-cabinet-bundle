<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Command;

use Dbp\Relay\CabinetBundle\TypesenseSync\TypesenseSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    private TypesenseSync $typesenseSync;

    public function __construct(TypesenseSync $typesenseSync)
    {
        parent::__construct();

        $this->typesenseSync = $typesenseSync;
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:cabinet:sync');
        $this->setDescription('Sync command');
        $this->addOption('--full', mode: InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $full = $input->getOption('full');
        $this->typesenseSync->sync($full);

        return 0;
    }
}
