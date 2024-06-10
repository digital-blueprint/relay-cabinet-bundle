<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\EventSubscriber;

use Dbp\Relay\BlobBundle\Event\AddFileDataByPostSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BlobAddFileSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct()
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AddFileDataByPostSuccessEvent::class => 'onPost',
        ];
    }

    public function onPost(AddFileDataByPostSuccessEvent $event)
    {
        // dump($event);
    }
}
