<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Command;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\CabinetBundle\Blob\BlobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeleteAllFilesCommand extends Command
{
    public function __construct(private readonly BlobService $blobService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:cabinet:delete-all-files');
        $this->setDescription('Delete all files from the cabinet blob bucket');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $fileIds = [];
            foreach ($this->blobService->getAllFiles() as $file) {
                $fileIds[] = $file['identifier'];
            }
        } catch (BlobApiError $e) {
            $output->writeln('<error>Error getting all files: '.$e->getMessage().' </error>');
            $output->writeln(print_r($e->getErrorId(), true));
            $output->writeln(print_r($e->getBlobErrorId(), true));

            return Command::FAILURE;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('About to delete '.count($fileIds).
            ' files from Blob storage. Are you sure you want to continue? (y/N) ', false);
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
            $output->writeln(print_r($e->getBlobErrorId(), true));

            return Command::FAILURE;
        }
    }
}
