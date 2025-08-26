<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Command;

use Dbp\Relay\CabinetBundle\TypesenseSync\TypesenseSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

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
        $this->addOption('--full', mode: InputOption::VALUE_NONE, description: 'Sync all records');
        $this->addOption('--ask', mode: InputOption::VALUE_NONE, description: 'Ask for confirmation before syncing');
        $this->addOption('--async', mode: InputOption::VALUE_NONE, description: 'Run the sync in the background');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $full = $input->getOption('full');
        $ask = $input->getOption('ask');
        $async = $input->getOption('async');

        if ($ask) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('About to sync. Are you sure you want to continue? (y/N) ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Action cancelled.');

                return Command::SUCCESS;
            }
        }

        $lastFullSyncDate = $this->typesenseSync->getLastFullSyncDate();
        if ($lastFullSyncDate !== null) {
            $output->writeln('<info>Last full sync:</info> '.$lastFullSyncDate->format(\DateTime::ATOM));
        }

        $lastSyncDate = $this->typesenseSync->getLastSyncDate();
        if ($lastSyncDate !== null) {
            $output->writeln('<info>Last sync:</info> '.$lastSyncDate->format(\DateTime::ATOM));
        }

        if ($async) {
            $this->typesenseSync->syncAsync($full);
        } else {
            $this->typesenseSync->sync($full);
        }

        return 0;
    }
}
