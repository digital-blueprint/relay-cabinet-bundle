<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Service;

use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;

class HealthCheck implements CheckInterface
{
    /**
     * @var DualDeliveryService
     */
    private $dd;

    /**
     * @var CabinetService
     */
    private $cabinet;

    public function __construct(DualDeliveryService $dd, CabinetService $cabinet)
    {
        $this->dd = $dd;
        $this->cabinet = $cabinet;
    }

    public function getName(): string
    {
        return 'cabinet';
    }

    private function checkDbConnection(): CheckResult
    {
        $result = new CheckResult('Check if we can connect to the DB');

        try {
            $this->cabinet->checkConnection();
        } catch (\Throwable $e) {
            $result->set(CheckResult::STATUS_FAILURE, $e->getMessage(), ['exception' => $e]);

            return $result;
        }
        $result->set(CheckResult::STATUS_SUCCESS);

        return $result;
    }

    public function check(CheckOptions $options): array
    {
        return [$this->checkDbConnection()];
    }
}
