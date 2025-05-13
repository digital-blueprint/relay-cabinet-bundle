<?php

declare(strict_types=1);

use Dbp\Relay\CabinetBundle\TypesenseProxy\TypesensePartitionedSearch;
use PHPUnit\Framework\TestCase;

class TypesenseProxyTest extends TestCase
{
    public function testMergeCountsSame()
    {
        $obj1 = json_decode('{"counts":[{"count":1234,"highlighted":"Person","value":"Person"},{"count":48,"highlighted":"DocumentFile","value":"DocumentFile"}],"field_name":"@type","sampled":false,"stats":{"total_values":2}}', flags: JSON_THROW_ON_ERROR);
        $obj2 = json_decode('{"counts":[{"count":1234,"highlighted":"Person","value":"Person"},{"count":48,"highlighted":"DocumentFile","value":"DocumentFile"}],"field_name":"@type","sampled":false,"stats":{"total_values":2}}', flags: JSON_THROW_ON_ERROR);
        $merged = TypesensePartitionedSearch::mergeCounts($obj1, $obj2);
        $this->assertSame(2, $merged->stats->total_values);
        $this->assertCount(2, $merged->counts);
        $this->assertFalse($merged->sampled);
        $this->assertSame(2468, $merged->counts[0]->count);
        $this->assertSame('Person', $merged->counts[0]->highlighted);
        $this->assertSame('Person', $merged->counts[0]->value);
        $this->assertSame(96, $merged->counts[1]->count);
        $this->assertSame('DocumentFile', $merged->counts[1]->highlighted);
        $this->assertSame('DocumentFile', $merged->counts[1]->value);
    }

    public function testMergeCountsDifferent()
    {
        $obj1 = json_decode('{"counts":[{"count":1234,"highlighted":"Person","value":"Person"},{"count":48,"highlighted":"DocumentFile","value":"DocumentFile"}],"field_name":"@type","sampled":false,"stats":{"total_values":2}}', flags: JSON_THROW_ON_ERROR);
        $obj2 = json_decode('{"counts":[{"count":234,"highlighted":"Person2","value":"Person2"},{"count":8,"highlighted":"DocumentFile2","value":"DocumentFile2"}],"field_name":"@type","sampled":true,"stats":{"total_values":2}}', flags: JSON_THROW_ON_ERROR);
        $merged = TypesensePartitionedSearch::mergeCounts($obj1, $obj2);
        $this->assertSame(4, $merged->stats->total_values);
        $this->assertCount(4, $merged->counts);
        $this->assertTrue($merged->sampled);
        $this->assertSame(1234, $merged->counts[0]->count);
        $this->assertSame('Person', $merged->counts[0]->highlighted);
        $this->assertSame('Person', $merged->counts[0]->value);
        $this->assertSame(48, $merged->counts[1]->count);
        $this->assertSame('DocumentFile', $merged->counts[1]->highlighted);
        $this->assertSame('DocumentFile', $merged->counts[1]->value);
        $this->assertSame(234, $merged->counts[2]->count);
        $this->assertSame('Person2', $merged->counts[2]->highlighted);
        $this->assertSame('Person2', $merged->counts[2]->value);
        $this->assertSame(8, $merged->counts[3]->count);
        $this->assertSame('DocumentFile2', $merged->counts[3]->highlighted);
        $this->assertSame('DocumentFile2', $merged->counts[3]->value);
    }

    public function testMergeFacetCounts()
    {
        $obj1 = json_decode('[{"counts":[{"count":1234,"highlighted":"Person","value":"Person"}],"field_name":"@type","sampled":false,"stats":{"total_values":1}}]', flags: JSON_THROW_ON_ERROR);
        $obj2 = json_decode('[{"counts":[{"count":456,"highlighted":"Person","value":"Person"}],"field_name":"@type","sampled":false,"stats":{"total_values":1}}]', flags: JSON_THROW_ON_ERROR);
        $merged = TypesensePartitionedSearch::mergeFaceCounts($obj1, $obj2);
        $this->assertCount(1, $merged);
        $this->assertSame('@type', $merged[0]->field_name);
        $this->assertSame(1690, $merged[0]->counts[0]->count);
    }

    public function testMergeFacetCountsDifferent()
    {
        $obj1 = json_decode('[{"counts":[{"count":1234,"highlighted":"Person","value":"Person"}],"field_name":"@type","sampled":false,"stats":{"total_values":1}}]', flags: JSON_THROW_ON_ERROR);
        $obj2 = json_decode('[{"counts":[{"count":456,"highlighted":"Person","value":"Person"}],"field_name":"@type2","sampled":false,"stats":{"total_values":1}}]', flags: JSON_THROW_ON_ERROR);
        $merged = TypesensePartitionedSearch::mergeFaceCounts($obj1, $obj2);
        $this->assertCount(2, $merged);
        $this->assertSame('@type', $merged[0]->field_name);
        $this->assertSame(1234, $merged[0]->counts[0]->count);
        $this->assertSame('@type2', $merged[1]->field_name);
        $this->assertSame(456, $merged[1]->counts[0]->count);
    }
}
