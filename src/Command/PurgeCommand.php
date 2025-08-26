<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Command;

use Dbp\Relay\CabinetBundle\TypesenseSync\TypesenseSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PurgeCommand extends Command
{
    public function __construct(private TypesenseSync $typesenseSync)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:cabinet:purge');
        $this->setDescription('Purge command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Delete all data, collections and keys in typesense? (y/N)', false);
        if (!$helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }

        $this->typesenseSync->removeSetup();

        return Command::SUCCESS;
    }
}
