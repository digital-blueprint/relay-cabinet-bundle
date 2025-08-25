<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CoreBundle\Cron\CronJobInterface;
use Dbp\Relay\CoreBundle\Cron\CronOptions;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class CronJob implements CronJobInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private TypesenseSync $typesenseSync)
    {
        $this->logger = new NullLogger();
    }

    public function getName(): string
    {
        return 'Cabinet sync check';
    }

    public function getInterval(): string
    {
        return '*/60 * * * *';
    }

    public function run(CronOptions $options): void
    {
        $this->typesenseSync->syncAsync();
    }
}
