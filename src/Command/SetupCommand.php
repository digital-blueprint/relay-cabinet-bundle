<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Command;

use Dbp\Relay\CabinetBundle\TypesenseSync\TypesenseSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetupCommand extends Command
{
    public function __construct(private TypesenseSync $typesenseSync)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:cabinet:setup');
        $this->setDescription('Setup command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->typesenseSync->ensureSetup();

        return Command::SUCCESS;
    }
}
