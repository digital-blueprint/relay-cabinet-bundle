<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Service;

use Dbp\Relay\CabinetBundle\TypesenseClient\SearchIndex;
use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;

class HealthCheck implements CheckInterface
{
    private CabinetService $cabinet;
    private SearchIndex $searchIndex;

    public function __construct(CabinetService $cabinet, SearchIndex $searchIndex)
    {
        $this->cabinet = $cabinet;
        $this->searchIndex = $searchIndex;
    }

    public function getName(): string
    {
        return 'cabinet';
    }

    private function checkMethod(string $description, callable $func): CheckResult
    {
        $result = new CheckResult($description);
        try {
            $func();
        } catch (\Throwable $e) {
            $result->set(CheckResult::STATUS_FAILURE, $e->getMessage(), ['exception' => $e]);

            return $result;
        }
        $result->set(CheckResult::STATUS_SUCCESS);

        return $result;
    }

    public function check(CheckOptions $options): array
    {
        return [
            $this->checkMethod('Check if we can connect to the DB', [$this->cabinet, 'checkConnection']),
            $this->checkMethod('Check if we can connect to Typesense', [$this->searchIndex, 'checkConnection']),
        ];
    }
}
