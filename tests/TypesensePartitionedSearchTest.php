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
        $result = TypesensePartitionedSearch::getPartitionsFilter('partition_field', 10, 2);
        $this->assertCount(2, $result);
        $this->assertEquals('partition_field: [0..4]', $result[0]['filter_by']);
        $this->assertEquals('partition_field: [5..9]', $result[1]['filter_by']);

        $result = TypesensePartitionedSearch::getPartitionsFilter('partition_field', 10, 1);
        $this->assertCount(1, $result);
        $this->assertEquals('partition_field: [0..9]', $result[0]['filter_by']);
    }

    public function testMergeResults(): void
    {
        $minimal = (object) ['hits' => [], 'found' => 0, 'facet_counts' => [], 'search_time_ms' => 0, 'search_cutoff' => false, 'request_params' => (object) ['collection_name' => 'foo'], 'page' => 1, 'out_of' => 42];
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

        $split = TypesensePartitionedSearch::splitJsonRequest($request, 2);
        $this->assertCount(2, $split);           // 2 partitions
        $this->assertCount(1, $split[0]);        // 1 page per partition (per_page=99 < 249)
        $this->assertStringContainsString('partitionKey: [0..49]', $split[0][0]->searches[0]->filter_by);
        $this->assertStringContainsString('partitionKey: [50..99]', $split[1][0]->searches[0]->filter_by);

        $merged = json_encode(TypesensePartitionedSearch::mergeJsonResponses($request, [['{"results":[]}'], ['{"results":[]}']], 2));
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

        $merged = TypesensePartitionedSearch::mergeJsonResponses($request, [[$response], [$response]], 2);
        $this->assertCount(1, $merged->results[0]->facet_counts);
        $this->assertSame(10024, $merged->results[0]->found_docs);
        $this->assertSame(6344, $merged->results[0]->found);
        $this->assertCount(2, $merged->results[0]->grouped_hits);
    }

    public function testGetRetryOverrides(): void
    {
        $request = '{
    "query_by": "person.familyName,person.givenName,file.base.fileName,objectType,person.stPersonNr,person.studId,person.identNrObfuscated,person.birthDate",
    "sort_by": "person.person:asc,@type:desc,objectType:desc",
    "collection": "cabinet",
    "q": "*",
    "facet_by": "@type",
    "filter_by": "base.isScheduledForDeletion:false",
    "max_facet_values": 1,
    "typo_tokens_threshold": 0,
    "page": 2,
    "per_page": 2
}';
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
    "found": 0,
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
    ]
}]}';

        [$needsRetry, $overrides] = TypesensePartitionedSearch::getRetryOverrides($request, json_decode($response, flags: JSON_THROW_ON_ERROR));
        $this->assertTrue($needsRetry);
        $this->assertSame(1, $overrides[0]->drop_tokens_threshold);

        $partitionRequests = TypesensePartitionedSearch::splitJsonRequest($request, 1);
        $this->assertSame(0, $partitionRequests[0][0]->searches[0]->drop_tokens_threshold);
        TypesensePartitionedSearch::applyRetryOverrides($partitionRequests, $overrides);
        $this->assertSame(1, $partitionRequests[0][0]->searches[0]->drop_tokens_threshold);
    }

    public function testSplitRequestPagesPerPartition(): void
    {
        $base = '{"query_by":"person.familyName","sort_by":"person.person:asc","collection":"cabinet","q":"*","page":1,"per_page":%d}';

        // Fits in one page per partition (249 <= 249 per partition with 1 partition)
        $split = TypesensePartitionedSearch::splitJsonRequest(sprintf($base, 249), 1);
        $this->assertCount(1, $split);     // 1 partition
        $this->assertCount(1, $split[0]); // 1 page

        // Just over one page per partition (250 > 249)
        $split = TypesensePartitionedSearch::splitJsonRequest(sprintf($base, 250), 1, true, 250);
        $this->assertCount(1, $split);
        $this->assertCount(2, $split[0]); // needs 2 pages

        // 1000 results, 1 partition => ceil(1000/1/249) = 5 pages
        $split = TypesensePartitionedSearch::splitJsonRequest(sprintf($base, 1000), 1, true, 1000, 10);
        $this->assertCount(1, $split);
        $this->assertCount(5, $split[0]);

        // 1000 results, 2 partitions => ceil(1000/2/249) = 3 pages per partition
        $split = TypesensePartitionedSearch::splitJsonRequest(sprintf($base, 1000), 2, true, 1000);
        $this->assertCount(2, $split);
        $this->assertCount(4, $split[0]);
        $this->assertCount(4, $split[1]);

        // 1000 results, 4 partitions => ceil(1000/4/249) = 2 pages per partition
        $split = TypesensePartitionedSearch::splitJsonRequest(sprintf($base, 1000), 4, true, 1000);
        $this->assertCount(4, $split);
        $this->assertCount(4, $split[0]);
    }

    public function testSplitRequestMultiPage(): void
    {
        $request = '{
    "query_by": "person.familyName",
    "sort_by": "person.person:asc",
    "collection": "cabinet",
    "q": "*",
    "page": 1,
    "per_page": 1000
}';
        // 2 partitions, 1000 results needed => ceil(1000/2/249) = 3 pages per partition
        $split = TypesensePartitionedSearch::splitJsonRequest($request, 2, true, 1000);
        $this->assertCount(2, $split);    // 2 partitions
        $this->assertCount(4, $split[0]); // 4 pages for partition 0
        $this->assertCount(4, $split[1]); // 4 pages for partition 1

        // Partition 0: pages 1, 2, 3 — all with the same filter
        $this->assertStringContainsString('partitionKey: [0..49]', $split[0][0]->searches[0]->filter_by);
        $this->assertSame(1, $split[0][0]->searches[0]->page);
        $this->assertSame(249, $split[0][0]->searches[0]->per_page);

        $this->assertStringContainsString('partitionKey: [0..49]', $split[0][1]->searches[0]->filter_by);
        $this->assertSame(2, $split[0][1]->searches[0]->page);

        $this->assertStringContainsString('partitionKey: [0..49]', $split[0][2]->searches[0]->filter_by);
        $this->assertSame(3, $split[0][2]->searches[0]->page);

        // Partition 1: pages 1, 2, 3
        $this->assertStringContainsString('partitionKey: [50..99]', $split[1][0]->searches[0]->filter_by);
        $this->assertSame(1, $split[1][0]->searches[0]->page);

        $this->assertStringContainsString('partitionKey: [50..99]', $split[1][1]->searches[0]->filter_by);
        $this->assertSame(2, $split[1][1]->searches[0]->page);

        $this->assertStringContainsString('partitionKey: [50..99]', $split[1][2]->searches[0]->filter_by);
        $this->assertSame(3, $split[1][2]->searches[0]->page);
    }

    public function testMergeJsonResponsesMultiPage(): void
    {
        $request = '{
    "query_by": "person.familyName",
    "sort_by": "person.familyName:asc",
    "collection": "cabinet",
    "q": "*",
    "page": 1,
    "per_page": 1000
}';

        // Build a response page. In real Typesense all pages of the same partition report the same
        // $partitionFound (total matching docs in that partition), regardless of page size.
        $makeResponse = function (int $startIndex, int $count, int $partitionFound, string $collection = 'cabinet'): string {
            $hits = [];
            for ($i = 0; $i < $count; ++$i) {
                $hits[] = ['document' => ['person' => ['familyName' => sprintf('Person%04d', $startIndex + $i)]],
                    'text_match' => 1];
            }

            return json_encode(['results' => [
                [
                    'facet_counts' => [],
                    'found' => $partitionFound,
                    'out_of' => 5000,
                    'page' => 1,
                    'request_params' => ['collection_name' => $collection, 'per_page' => 249, 'q' => '*', 'first_q' => '*'],
                    'search_cutoff' => false,
                    'search_time_ms' => 10,
                    'hits' => $hits,
                ],
            ]]);
        };

        // 2 partitions, 3 pages each — nested as [partition][page]
        // Partition 0 total found = 500, split across pages: 249 + 249 + 2 hits
        // Partition 1 total found = 500, split across pages: 249 + 249 + 2 hits
        // Total pool: 1000 hits; merged found = 500 + 500 = 1000
        // page 1 with per_page=1000 should return all 1000 hits
        $responses = [
            // partition 0
            [
                $makeResponse(0, 249, 500),   // page 1
                $makeResponse(249, 249, 500), // page 2
                $makeResponse(498, 2, 500),   // page 3 (only 2 left)
            ],
            // partition 1
            [
                $makeResponse(500, 249, 500), // page 1
                $makeResponse(749, 249, 500), // page 2
                $makeResponse(998, 2, 500),   // page 3 (only 2 left)
            ],
        ];

        $merged = TypesensePartitionedSearch::mergeJsonResponses($request, $responses, 2);
        // found is taken from page 1 of each partition, then summed across partitions
        $this->assertSame(1000, $merged->found);
        // page 1 with per_page=1000 => all 1000 hits returned
        $this->assertCount(747, $merged->hits);
        $this->assertSame(1, $merged->page);
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
    "page": 2,
    "per_page": 2
}';
        $split = TypesensePartitionedSearch::splitJsonRequest($request, 2);
        $this->assertCount(2, $split);           // 2 partitions
        $this->assertCount(1, $split[0]);        // 1 page per partition (per_page=2 < 249)
        $this->assertStringContainsString('partitionKey: [0..49]', $split[0][0]->searches[0]->filter_by);
        $this->assertStringContainsString('partitionKey: [50..99]', $split[1][0]->searches[0]->filter_by);

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
        },
        {
            "document": {
            }
        }
    ]
}]}';

        $merged = TypesensePartitionedSearch::mergeJsonResponses($request, [[$response], [$response]], 2);
        $this->assertCount(1, $merged->facet_counts);
        $this->assertSame(6344, $merged->found);
        $this->assertCount(2, $merged->hits);
        $this->assertSame(2, $merged->page);
        $this->assertSame(5012, $merged->out_of);
        $this->assertSame(false, $merged->search_cutoff);
    }
}
