<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobBundle\Event\AddFileDataByPostSuccessEvent;
use Dbp\Relay\BlobBundle\Event\ChangeFileDataByPatchSuccessEvent;
use Dbp\Relay\BlobBundle\Event\DeleteFileDataByDeleteSuccessEvent;
use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Dbp\Relay\CabinetBundle\TypesenseSync\TypesenseSync;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

class BlobSubscriber implements EventSubscriberInterface
{
    private ConfigurationService $config;
    private TypesenseSync $typesenseSync;
    private MessageBusInterface $messageBus;

    public function __construct(ConfigurationService $config, TypesenseSync $typesenseSync, MessageBusInterface $messageBus)
    {
        $this->config = $config;
        $this->typesenseSync = $typesenseSync;
        $this->messageBus = $messageBus;
    }

    private function isForCabinet(FileData $fileData): bool
    {
        $bucket = $fileData->getBucket();
        if ($bucket->getBucketID() !== $this->config->getBlobBucketId()) {
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

    public function onFileAdded(AddFileDataByPostSuccessEvent $event)
    {
        $fileData = $event->getFileData();
        if (!$this->isForCabinet($fileData)) {
            return;
        }

        $this->messageBus->dispatch(new BlobEventTask('upsert', $fileData->getBucket()->getBucketID(), $fileData->getIdentifier()));
    }

    public function onFileChanged(ChangeFileDataByPatchSuccessEvent $event)
    {
        $fileData = $event->getFileData();
        if (!$this->isForCabinet($fileData)) {
            return;
        }

        $this->messageBus->dispatch(new BlobEventTask('upsert', $fileData->getBucket()->getBucketID(), $fileData->getIdentifier()));
    }

    public function onFileRemoved(DeleteFileDataByDeleteSuccessEvent $event)
    {
        // FIXME: DeleteFileDataByDeleteSuccessEvent currently has not bucket set in the latest blob release.
        // Should be fixed with the next release.
        return;
        $fileData = $event->getFileData();

        if (!$this->isForCabinet($fileData)) {
            return;
        }

        $this->messageBus->dispatch(new BlobEventTask('delete', $fileData->getBucket()->getBucketID(), $fileData->getIdentifier()));
    }

    #[AsMessageHandler]
    public function handleBlobEventTask(BlobEventTask $task): void
    {
        if ($task->getBucketId() !== $this->config->getBlobBucketId()) {
            // Check again, in case the job is handled after the config has changed
            return;
        }

        if ($task->getAction() === 'upsert') {
            $this->typesenseSync->upsertFile($task->getFileId());
        } elseif ($task->getAction() === 'delete') {
            $this->typesenseSync->deleteFile($task->getFileId());
        } else {
            throw new \RuntimeException('unsupported task: '.$task->getAction());
        }
    }
}
