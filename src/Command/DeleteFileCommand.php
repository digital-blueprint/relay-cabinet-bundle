<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Command;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\CabinetBundle\Blob\BlobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteFileCommand extends Command
{
    private BlobService $blobService;

    public function __construct(BlobService $blobService)
    {
        parent::__construct();

        $this->blobService = $blobService;
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:cabinet:delete-file');
        $this->setDescription('Delete a file from the cabinet blob bucket');
        $this->addArgument('id', InputArgument::REQUIRED, 'The ID of the file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fileId = $input->getArgument('id');

        try {
            $this->blobService->deleteFile($fileId);
            $output->writeln("<info>File deleted successfully: $fileId</info>");

            return Command::SUCCESS;
        } catch (BlobApiError $e) {
            $output->writeln('<error>Error deleting file: '.$e->getMessage().' </error>');
            $output->writeln(print_r($e->getErrorId(), true));
            $output->writeln(print_r($e->getErrorDetails(), true));

            return Command::FAILURE;
        }
    }
}
