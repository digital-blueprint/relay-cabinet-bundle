<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Command;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\CabinetBundle\Service\BlobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class UploadFileCommand extends Command
{
    private BlobService $blobService;

    public function __construct(BlobService $blobService)
    {
        parent::__construct();

        $this->blobService = $blobService;
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:cabinet:upload-file');
        $this->setDescription('Upload a file to the cabinet blob bucket');

        $this->addArgument('filepath', InputArgument::REQUIRED, 'The path to the file to upload');
        $this->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'The type of the file');
        $this->addOption('metadata', 'm', InputOption::VALUE_OPTIONAL, 'The metadata of the file');
        $this->addOption('filename', 'f', InputOption::VALUE_REQUIRED,
            'The filename, defaults to the filename of the given filepath');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filepath = $input->getArgument('filepath');
        $type = $input->getOption('type');
        $metadata = $input->getOption('metadata');

        $filesystem = new Filesystem();

        if (!$filesystem->exists($filepath)) {
            $output->writeln("<error>File not found: $filepath</error>");

            return Command::FAILURE;
        }

        $filename = $input->getOption('filename') ?? basename($filepath);
        $payload = file_get_contents($filepath);

        if ($payload === false) {
            $output->writeln("<error>Unable to read file: $filepath</error>");

            return Command::FAILURE;
        }

        try {
            $fileId = $this->blobService->uploadFile($filename, $payload, $type, $metadata);
            $output->writeln("<info>File uploaded successfully: $fileId</info>");

            return Command::SUCCESS;
        } catch (BlobApiError $e) {
            $output->writeln('<error>Error uploading file: '.$e->getMessage().' </error>');
            $output->writeln(print_r($e->getErrorId(), true));
            $output->writeln(print_r($e->getErrorDetails(), true));

            return Command::FAILURE;
        }
    }
}
