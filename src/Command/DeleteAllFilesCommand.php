<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Command;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\CabinetBundle\Service\BlobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeleteAllFilesCommand extends Command
{
    private BlobService $blobService;

    public function __construct(BlobService $blobService)
    {
        parent::__construct();

        $this->blobService = $blobService;
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:cabinet:delete-all-files');
        $this->setDescription('Delete all files from the cabinet blob bucket');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fileIds = [];
        foreach ($this->blobService->getAllFiles() as $file) {
            $fileIds[] = $file['identifier'];
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('About to delete '.count($fileIds).' files. Are you sure you want to continue? (y/N) ', false);
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('Action cancelled.');

            return Command::SUCCESS;
        }

        try {
            foreach ($fileIds as $id) {
                $output->writeln("<info>Deleting $id</info>");
                $this->blobService->deleteFile($id);
            }
            $output->writeln('<info>All files deleted successfully</info>');

            return Command::SUCCESS;
        } catch (BlobApiError $e) {
            $output->writeln('<error>Error deleting file: '.$e->getMessage().' </error>');
            $output->writeln(print_r($e->getErrorId(), true));
            $output->writeln(print_r($e->getErrorDetails(), true));

            return Command::FAILURE;
        }
    }
}
