<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Dbp\Relay\CoreBundle\TestUtils\UserAuthTrait;

class ApiTest extends ApiTestCase
{
    use UserAuthTrait;

    public function testKernel()
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->assertNotNull($container);
    }
}
