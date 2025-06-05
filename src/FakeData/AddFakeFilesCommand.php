<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\FakeData;

use Dbp\Relay\CabinetBundle\Blob\BlobService;
use Dbp\Relay\CabinetBundle\TypesenseSync\TypesenseClient;
use Dbp\Relay\CabinetBundle\TypesenseSync\TypesenseSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;
use Symfony\Component\Uid\Uuid;

class AddFakeFilesCommand extends Command
{
    private TypesenseClient $searchIndex;
    private BlobService $blobService;
    private EventDispatcherInterface $eventDispatcher;
    private TypesenseSync $typesenseSync;

    public function __construct(TypesenseClient $searchIndex, BlobService $blobService, TypesenseSync $typesenseSync, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();

        $this->searchIndex = $searchIndex;
        $this->blobService = $blobService;
        $this->eventDispatcher = $eventDispatcher;
        $this->typesenseSync = $typesenseSync;
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:cabinet:add-fake-files');
        $this->setDescription('Add fake files to blob and/or cabinet');

        $this->addOption('person-id', null, InputOption::VALUE_REQUIRED, 'If the files should be added for only one specific person');
        $this->addOption('count', null, InputOption::VALUE_REQUIRED, 'The the number of files to add', '10');
        $this->addOption('no-blob', null, InputOption::VALUE_NONE, 'Don\'t add the files to blob, only to typesense');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->eventDispatcher->getListeners(FakeFileEvent::class)) {
            throw new \RuntimeException('No event listener registered for generating fake data');
        }

        $count = (int) $input->getOption('count');

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('About to generate '.$count.' fake files for cabinet. Are you sure you want to continue? (y/N) ', false);
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('Action cancelled.');

            return Command::SUCCESS;
        }

        $collectionName = $this->searchIndex->getCollectionName();
        $personIds = $this->typesenseSync->getAllPersonIds($collectionName);
        $personId = $input->getOption('person-id');
        if ($personId !== null) {
            if (!in_array($personId, $personIds, true)) {
                throw new \RuntimeException('person-id not found');
            }
            $personIds = [$personId];
        } else {
            if (count($personIds) === 0) {
                throw new \RuntimeException('No person in typesense');
            }
        }

        $getRandom = function (array $values) {
            return $values[array_rand($values)];
        };

        $typesenseOnly = (bool) $input->getOption('no-blob');
        if ($typesenseOnly) {
            $entries = [];
            for ($i = 0; $i < $count; ++$i) {
                $event = new FakeFileEvent($i + 1, $count, $getRandom($personIds));
                $event = $this->eventDispatcher->dispatch($event);
                $filename = $event->getFileName();
                $guesser = new FileinfoMimeTypeGuesser();
                $mimeType = $guesser->guessMimeType($event->getFilePath());
                if (!$mimeType) {
                    throw new \RuntimeException('No mime type guessed');
                }
                $fileData = [
                    'identifier' => Uuid::v7()->toRfc4122(),
                    'fileName' => $filename,
                    'mimeType' => $mimeType,
                    'dateCreated' => (new \DateTimeImmutable())->format(\DateTime::ATOM),
                    'dateModified' => (new \DateTimeImmutable())->format(\DateTime::ATOM),
                    'deleteAt' => null,
                    'metadata' => $event->getMetadata(),
                ];
                $entries[] = $fileData;
            }
            $collectionName = $this->searchIndex->getCollectionName();
            $this->typesenseSync->upsertMultipleFileData($collectionName, $entries);
        } else {
            for ($i = 0; $i < $count; ++$i) {
                $event = new FakeFileEvent($i + 1, $count, $getRandom($personIds));
                $event = $this->eventDispatcher->dispatch($event);
                $payload = file_get_contents($event->getFilePath());
                $filename = $event->getFileName();
                $type = $event->getBlobType();
                $metadata = $event->getMetadata();
                $this->blobService->uploadFile($filename, $payload, $type, $metadata);
            }
        }

        return Command::SUCCESS;
    }
}
