<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiTest extends KernelTestCase
{
    public function testKernel()
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->assertNotNull($container);
        $this->assertTrue($container->has('monolog.logger.dbp_relay_cabinet_audit'));
    }
}
