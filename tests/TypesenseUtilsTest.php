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

    public function testDecodeJsonLines()
    {
        $jsonl = '{"name": "John", "age": 30}'."\n".'{"name": "Jane", "age": 35}';
        $result = iterator_to_array(Utils::decodeJsonLines($jsonl, true));

        $expected = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 35],
        ];

        $this->assertSame($expected, $result);
    }

    public function testDecodeJsonLinesWithEmptyString()
    {
        $result = iterator_to_array(Utils::decodeJsonLines('', true));
        $this->assertSame([], $result);

        $result = iterator_to_array(Utils::decodeJsonLines("42\n", true));
        $this->assertSame([42], $result);
    }
}
