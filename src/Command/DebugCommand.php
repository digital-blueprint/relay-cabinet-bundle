<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Command;

use Dbp\Relay\CabinetBundle\Service\CabinetService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugCommand extends Command
{
    private CabinetService $cabinetService;

    public function __construct(CabinetService $cabinetService)
    {
        parent::__construct();

        $this->cabinetService = $cabinetService;
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('dbp:relay-cabinet:debug');
        $this
            ->setDescription('Debug command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');
        $identifier = $input->getArgument('identifier');

        switch ($action) {
            case 'test':
                $output->writeln('Test...');
                break;
            default:
                $output->writeln('Action not found!');

                return 1;
        }

        return 0;
    }
}
