<?php

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CoreBundle\Cron\CronJobInterface;
use Dbp\Relay\CoreBundle\Cron\CronOptions;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class CronJob implements CronJobInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private TypesenseSync $typesenseSync;

    public function __construct(TypesenseSync $typesenseSync)
    {
        $this->typesenseSync = $typesenseSync;
        $this->logger = new NullLogger();
    }
    public function getName(): string
    {
        return "Cabinet sync check";
    }

    public function getInterval(): string
    {
        return "*/5 * * * *";
    }

    public function run(CronOptions $options): void
    {
        $lock = $this->typesenseSync->getSyncLock();
        if ($lock->acquire(false)) {

        } else {
            $this->logger->info("Sync lock not free, skipping sync");
        }
    }
}