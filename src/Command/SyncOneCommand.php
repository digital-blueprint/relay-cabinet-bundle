<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Command;

use Dbp\Relay\CabinetBundle\TypesenseSync\TypesenseSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncOneCommand extends Command
{
    private TypesenseSync $typesenseSync;

    public function __construct(TypesenseSync $typesenseSync)
    {
        parent::__construct();

        $this->typesenseSync = $typesenseSync;
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:cabinet:sync-one');
        $this->setDescription('Sync command');
        $this->addArgument('obfuscated-id', InputArgument::REQUIRED, 'obfuscated id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $obfuscatedId = $input->getArgument('obfuscated-id');
        $this->typesenseSync->syncOne($obfuscatedId);

        return 0;
    }
}
