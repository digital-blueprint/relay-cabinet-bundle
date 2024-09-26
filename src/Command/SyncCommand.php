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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $full = $input->getOption('full');
        $ask = $input->getOption('ask');

        if ($ask) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $connectionBaseUrl = $this->typesenseSync->getConnectionBaseUrl();
            $question = new ConfirmationQuestion('About to sync to '.$connectionBaseUrl.'. Are you sure you want to continue? (y/N) ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Action cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->typesenseSync->sync($full);

        return 0;
    }
}
