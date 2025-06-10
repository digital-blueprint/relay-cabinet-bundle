<?php

declare(strict_types=1);

use Dbp\Relay\CabinetBundle\TypesenseProxy\TypesensePartitionedSearch;
use PHPUnit\Framework\TestCase;

class TypesensePartitionedSearchTest extends TestCase
{
    public function testMergeCountsSame()
    {
        $obj1 = json_decode('{"counts":[{"count":123,"highlighted":"Person","value":"Person"},{"count":456,"highlighted":"DocumentFile","value":"DocumentFile"}],"field_name":"@type","sampled":false,"stats":{"total_values":2}}', flags: JSON_THROW_ON_ERROR);
        $obj2 = json_decode('{"counts":[{"count":456,"highlighted":"Person","value":"Person"},{"count":123,"highlighted":"DocumentFile","value":"DocumentFile"}],"field_name":"@type","sampled":false,"stats":{"total_values":2}}', flags: JSON_THROW_ON_ERROR);
        $merged = TypesensePartitionedSearch::mergeCounts($obj1, $obj2);
        $this->assertSame(2, $merged->stats->total_values);
        $this->assertCount(2, $merged->counts);
        $this->assertFalse($merged->sampled);
        $this->assertSame(579, $merged->counts[0]->count);
        $this->assertSame('DocumentFile', $merged->counts[0]->highlighted);
        $this->assertSame('DocumentFile', $merged->counts[0]->value);
        $this->assertSame(579, $merged->counts[1]->count);
        $this->assertSame('Person', $merged->counts[1]->highlighted);
        $this->assertSame('Person', $merged->counts[1]->value);
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
        $this->assertSame(234, $merged->counts[1]->count);
        $this->assertSame('Person2', $merged->counts[1]->highlighted);
        $this->assertSame('Person2', $merged->counts[1]->value);
        $this->assertSame(48, $merged->counts[2]->count);
        $this->assertSame('DocumentFile', $merged->counts[2]->highlighted);
        $this->assertSame('DocumentFile', $merged->counts[2]->value);
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

    public function testSingleFieldAscending()
    {
        $items = [
            (object) ['document' => (object) ['name' => 'Charlie']],
            (object) ['document' => (object) ['name' => 'Alice']],
            (object) ['document' => (object) ['name' => 'Bob']],
        ];

        $sortSpec = 'name:asc';
        $sortFunction = TypesensePartitionedSearch::createSortFunction($sortSpec);

        usort($items, $sortFunction);

        $this->assertEquals('Alice', $items[0]->document->name);
        $this->assertEquals('Bob', $items[1]->document->name);
        $this->assertEquals('Charlie', $items[2]->document->name);
    }

    public function testSingleFieldDescending()
    {
        $items = [
            (object) ['document' => (object) ['name' => 'Charlie']],
            (object) ['document' => (object) ['name' => 'Alice']],
            (object) ['document' => (object) ['name' => 'Bob']],
        ];

        $sortSpec = 'name:desc';
        $sortFunction = TypesensePartitionedSearch::createSortFunction($sortSpec);

        usort($items, $sortFunction);

        $this->assertEquals('Charlie', $items[0]->document->name);
        $this->assertEquals('Bob', $items[1]->document->name);
        $this->assertEquals('Alice', $items[2]->document->name);
    }

    public function testMultipleFields()
    {
        $items = [
            (object) ['document' => (object) ['group' => 'A', 'rank' => 3]],
            (object) ['document' => (object) ['group' => 'B', 'rank' => 1]],
            (object) ['document' => (object) ['group' => 'A', 'rank' => 1]],
            (object) ['document' => (object) ['group' => 'B', 'rank' => 2]],
        ];

        $sortSpec = 'group:asc,rank:asc';
        $sortFunction = TypesensePartitionedSearch::createSortFunction($sortSpec);

        usort($items, $sortFunction);

        $this->assertEquals('A', $items[0]->document->group);
        $this->assertEquals(1, $items[0]->document->rank);
        $this->assertEquals('A', $items[1]->document->group);
        $this->assertEquals(3, $items[1]->document->rank);
        $this->assertEquals('B', $items[2]->document->group);
        $this->assertEquals(1, $items[2]->document->rank);
        $this->assertEquals('B', $items[3]->document->group);
        $this->assertEquals(2, $items[3]->document->rank);
    }

    public function testNestedFields()
    {
        $items = [
            (object) ['document' => (object) ['user' => (object) ['name' => 'Charlie']]],
            (object) ['document' => (object) ['user' => (object) ['name' => 'Alice']]],
            (object) ['document' => (object) ['user' => (object) ['name' => 'Bob']]],
        ];

        $sortSpec = 'user.name:asc';
        $sortFunction = TypesensePartitionedSearch::createSortFunction($sortSpec);

        usort($items, $sortFunction);

        $this->assertEquals('Alice', $items[0]->document->user->name);
        $this->assertEquals('Bob', $items[1]->document->user->name);
        $this->assertEquals('Charlie', $items[2]->document->user->name);
    }

    public function testComplexExample()
    {
        $items = [
            (object) ['document' => (object) [
                'person' => (object) ['person' => 'Alice'],
                '@type' => 'User',
                'objectType' => 'Standard',
            ]],
            (object) ['document' => (object) [
                'person' => (object) ['person' => 'Bob'],
                '@type' => 'Admin',
                'objectType' => 'Premium',
            ]],
            (object) ['document' => (object) [
                'person' => (object) ['person' => 'Alice'],
                '@type' => 'Admin',
                'objectType' => 'Standard',
            ]],
            (object) ['document' => (object) [
                'person' => (object) ['person' => 'Alice'],
                '@type' => 'Admin',
                'objectType' => 'Premium',
            ]],
        ];

        $sortSpec = 'person.person:asc,@type:desc,objectType:desc';
        $sortFunction = TypesensePartitionedSearch::createSortFunction($sortSpec);

        usort($items, $sortFunction);

        $this->assertEquals('Alice', $items[0]->document->person->person);
        $this->assertEquals('Alice', $items[1]->document->person->person);
        $this->assertEquals('Alice', $items[2]->document->person->person);
        $this->assertEquals('Bob', $items[3]->document->person->person);

        $this->assertEquals('User', $items[0]->document->{'@type'});
        $this->assertEquals('Admin', $items[1]->document->{'@type'});
        $this->assertEquals('Admin', $items[2]->document->{'@type'});

        $this->assertEquals('Standard', $items[0]->document->objectType);
        $this->assertEquals('Standard', $items[1]->document->objectType);
        $this->assertEquals('Premium', $items[2]->document->objectType);
    }

    public function testNonexistentFields()
    {
        $items = [
            (object) ['document' => (object) ['name' => 'Alice']],
            (object) ['document' => (object) ['name' => 'Bob', 'age' => 30]],
            (object) ['document' => (object) ['name' => 'Charlie']],
        ];

        $sortSpec = 'age:asc,name:asc';
        $sortFunction = TypesensePartitionedSearch::createSortFunction($sortSpec);

        usort($items, $sortFunction);

        $this->assertEquals('Alice', $items[0]->document->name);
        $this->assertEquals('Charlie', $items[1]->document->name);
        $this->assertEquals('Bob', $items[2]->document->name);
        $this->assertEquals(30, $items[2]->document->age);
    }

    public function testTextMatch()
    {
        $items = [
            (object) ['document' => (object) ['name' => 'Charlie'], 'text_match' => 3],
            (object) ['document' => (object) ['name' => 'Alice'], 'text_match' => 6],
            (object) ['document' => (object) ['name' => 'Bob'], 'text_match' => 1],
        ];

        $sortSpec = '_text_match:desc';
        $sortFunction = TypesensePartitionedSearch::createSortFunction($sortSpec);

        usort($items, $sortFunction);

        $this->assertEquals('Alice', $items[0]->document->name);
        $this->assertEquals('Charlie', $items[1]->document->name);
        $this->assertEquals('Bob', $items[2]->document->name);
    }

    public function testBasicPartitioning(): void
    {
        $result = TypesensePartitionedSearch::getPartitions('partition_field', 10, 2);
        $this->assertCount(2, $result);
        $this->assertEquals('partition_field: [0..4]', $result[0]);
        $this->assertEquals('partition_field: [5..9]', $result[1]);

        $result = TypesensePartitionedSearch::getPartitions('partition_field', 10, 1);
        $this->assertCount(1, $result);
        $this->assertEquals('partition_field: [0..9]', $result[0]);
    }

    public function testMergeResults(): void
    {
        $minimal = (object) ['hits' => [], 'found' => 0, 'facet_counts' => [], 'search_time_ms' => 0, 'search_cutoff' => false, 'request_params' => (object) [], 'page' => 1, 'out_of' => 42];
        $res = TypesensePartitionedSearch::mergeResults($minimal, $minimal);
        $this->assertSame([], $res->hits);
    }

    public function testMergeError(): void
    {
        $error = json_decode('{"code":404,"error":"Could not find a field named `person.nope` in the schema."}');
        $res = TypesensePartitionedSearch::mergeResults($error, (object) []);
        $this->assertSame($error, $res);
        $res = TypesensePartitionedSearch::mergeResults((object) [], $error);
        $this->assertSame($error, $res);
    }

    public function testSplitMultiSearch(): void
    {
        $request = '{
    "searches": [
        {
            "query_by": "person.familyName,person.givenName,file.base.fileName,objectType,person.stPersonNr,person.studId,person.identNrObfuscated,person.birthDate",
            "sort_by": "person.person:asc,@type:desc,objectType:desc",
            "collection": "cabinet",
            "q": "*",
            "facet_by": "@type",
            "filter_by": "base.isScheduledForDeletion:false",
            "group_by": "@type",
            "max_facet_values": 1,
            "page": 1,
            "per_page": 99
        }
    ]
}';
        $this->assertSame($request, TypesensePartitionedSearch::splitJsonRequest($request, 1)[0]);

        $split = TypesensePartitionedSearch::splitJsonRequest($request, 2);
        $this->assertCount(2, $split);
        $this->assertStringContainsString('partitionKey: [0..49]', $split[0]);
        $this->assertStringContainsString('partitionKey: [50..99]', $split[1]);

        $merged = TypesensePartitionedSearch::mergeJsonResponses($request, ['{"results":[]}',  '{"results":[]}'], 2);
        $this->assertSame('{"results":[]}', $merged);

        $response = '{
    "results": [
        {
            "facet_counts": [
                {
                    "field_name": "@type",
                    "sampled": false,
                    "counts": [
                        {
                            "count": 3172,
                            "highlighted": "Person",
                            "value": "Person"
                        }
                    ],
                    "stats": {
                        "total_values": 1
                    }
                }
            ],
            "found": 3172,
            "out_of": 5012,
            "page": 1,
            "request_params": {
                "collection_name": "cabinet",
                "first_q": "*",
                "per_page": 250,
                "q": "*"
            },
            "search_cutoff": false,
            "search_time_ms": 101,
            "grouped_hits": [
                {
                    "found": 1,
                    "group_key": [
                        "12344"
                    ],
                    "hits": [
                        {
                            "document": {
                            }
                        }
                    ]
                }
            ],
            "found_docs": 5012
        }
    ]
}';

        $merged = json_decode(TypesensePartitionedSearch::mergeJsonResponses($request, [$response, $response], 2), flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1, $merged->results[0]->facet_counts);
        $this->assertSame(10024, $merged->results[0]->found_docs);
        $this->assertSame(6344, $merged->results[0]->found);
        $this->assertCount(2, $merged->results[0]->grouped_hits);
    }

    public function testSplitSingleSearch(): void
    {
        $request = '{
    "query_by": "person.familyName,person.givenName,file.base.fileName,objectType,person.stPersonNr,person.studId,person.identNrObfuscated,person.birthDate",
    "sort_by": "person.person:asc,@type:desc,objectType:desc",
    "collection": "cabinet",
    "q": "*",
    "facet_by": "@type",
    "filter_by": "base.isScheduledForDeletion:false",
    "max_facet_values": 1,
    "page": 1,
    "per_page": 99
}';
        $this->assertSame($request, TypesensePartitionedSearch::splitJsonRequest($request, 1)[0]);

        $split = TypesensePartitionedSearch::splitJsonRequest($request, 2);
        $this->assertCount(2, $split);
        $this->assertStringContainsString('partitionKey: [0..49]', $split[0]);
        $this->assertStringContainsString('partitionKey: [50..99]', $split[1]);

        $response = '{"results": [{
    "facet_counts": [
        {
            "field_name": "@type",
            "sampled": false,
            "counts": [
                {
                    "count": 3172,
                    "highlighted": "Person",
                    "value": "Person"
                }
            ],
            "stats": {
                "total_values": 1
            }
        }
    ],
    "found": 3172,
    "out_of": 5012,
    "page": 1,
    "request_params": {
        "collection_name": "cabinet",
        "first_q": "*",
        "per_page": 250,
        "q": "*"
    },
    "search_cutoff": false,
    "search_time_ms": 101,
    "hits": [
        {
            "document": {
            }
        }
    ]
}]}';

        $merged = json_decode(TypesensePartitionedSearch::mergeJsonResponses($request, [$response, $response], 2), flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1, $merged->facet_counts);
        $this->assertSame(6344, $merged->found);
        $this->assertCount(2, $merged->hits);
    }
}
