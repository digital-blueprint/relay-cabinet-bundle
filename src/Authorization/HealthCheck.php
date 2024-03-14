<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Authorization;

use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;

class HealthCheck implements CheckInterface
{
    private $authorizationService;

    public function __construct(AuthorizationService $authorizationService)
    {
        $this->authorizationService = $authorizationService;
    }

    public function getName(): string
    {
        return 'cabinet-authorization';
    }

    public function check(CheckOptions $options): array
    {
        $result = new CheckResult('Validate Cabinet access control policies');

        $result->set(CheckResult::STATUS_SUCCESS);
        try {
            $this->authorizationService->validateConfiguration();
        } catch (\Throwable $e) {
            $result->set(CheckResult::STATUS_FAILURE, $e->getMessage(), ['exception' => $e]);
        }

        return [$result];
    }
}
