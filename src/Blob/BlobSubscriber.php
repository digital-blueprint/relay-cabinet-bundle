<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobBundle\Event\AddFileDataByPostSuccessEvent;
use Dbp\Relay\BlobBundle\Event\ChangeFileDataByPatchSuccessEvent;
use Dbp\Relay\BlobBundle\Event\DeleteFileDataByDeleteSuccessEvent;
use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Dbp\Relay\CabinetBundle\TypesenseSync\DocumentTranslator;
use Dbp\Relay\CabinetBundle\TypesenseSync\TypesenseSync;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

class BlobSubscriber implements EventSubscriberInterface
{
    private ConfigurationService $config;
    private DocumentTranslator $translator;
    private TypesenseSync $typesenseSync;
    private MessageBusInterface $messageBus;

    public function __construct(ConfigurationService $config, DocumentTranslator $translator, TypesenseSync $typesenseSync, MessageBusInterface $messageBus)
    {
        $this->config = $config;
        $this->translator = $translator;
        $this->typesenseSync = $typesenseSync;
        $this->messageBus = $messageBus;
    }

    private function isForCabinet(FileData $fileData): bool
    {
        $bucket = $fileData->getBucket();
        if ($bucket->getBucketID() !== $this->config->getBlobBucketId()) {
            return false;
        }
        if ($fileData->getPrefix() !== $this->config->getBlobBucketPrefix()) {
            return false;
        }

        return true;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AddFileDataByPostSuccessEvent::class => 'onFileAdded',
            ChangeFileDataByPatchSuccessEvent::class => 'onFileChanged',
            DeleteFileDataByDeleteSuccessEvent::class => 'onFileRemoved',
        ];
    }

    private function translateMetadata(FileData $fileData): array
    {
        $metadata = json_decode($fileData->getMetadata(), associative: true, flags: JSON_THROW_ON_ERROR);
        $objectType = $metadata['objectType'];
        $input = [
            'id' => $fileData->getIdentifier(),
            'fileSource' => $fileData->getBucket()->getBucketID(),
            'fileName' => $fileData->getFileName(),
            'metadata' => $metadata,
        ];

        return $this->translator->translateDocument($objectType, $input);
    }

    public function onFileAdded(AddFileDataByPostSuccessEvent $event)
    {
        $fileData = $event->getFileData();
        if (!$this->isForCabinet($fileData)) {
            return;
        }

        $translated = $this->translateMetadata($fileData);
        $this->messageBus->dispatch(new TypesenseTask('upsert', $translated));
    }

    public function onFileChanged(ChangeFileDataByPatchSuccessEvent $event)
    {
        $fileData = $event->getFileData();
        if (!$this->isForCabinet($fileData)) {
            return;
        }

        $translated = $this->translateMetadata($fileData);
        $this->messageBus->dispatch(new TypesenseTask('upsert', $translated));
    }

    public function onFileRemoved(DeleteFileDataByDeleteSuccessEvent $event)
    {
        $fileData = $event->getFileData();
        if (!$this->isForCabinet($fileData)) {
            return;
        }

        $translated = $this->translateMetadata($fileData);
        $this->messageBus->dispatch(new TypesenseTask('delete', $translated));
    }

    #[AsMessageHandler]
    public function handleTypesenseTask(TypesenseTask $task): void
    {
        if ($task->getAction() === 'upsert') {
            $this->typesenseSync->upsertPartialFile($task->getDocument());
        } elseif ($task->getAction() === 'delete') {
            $this->typesenseSync->deletePartialFile($task->getDocument());
        } else {
            throw new \RuntimeException('unsupported task: '.$task->getAction());
        }
    }
}
