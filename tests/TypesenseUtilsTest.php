<?php

declare(strict_types=1);

use Dbp\Relay\CabinetBundle\TypesenseSync\Utils;
use PHPUnit\Framework\TestCase;

class TypesenseUtilsTest extends TestCase
{
    public function testGetPartitionIndex()
    {
        $this->assertSame(0, Utils::getPartitionIndex(2, 10, 100));
        $this->assertSame(0, Utils::getPartitionIndex(2, 49, 100));
        $this->assertSame(1, Utils::getPartitionIndex(2, 50, 100));
        $this->assertSame(1, Utils::getPartitionIndex(2, 99, 100));
    }
}
