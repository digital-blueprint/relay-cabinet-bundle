<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Dbp\Relay\CoreBundle\TestUtils\UserAuthTrait;

class ApiTest extends ApiTestCase
{
    use UserAuthTrait;

    public function test()
    {
        $this->expectNotToPerformAssertions();
    }
}
