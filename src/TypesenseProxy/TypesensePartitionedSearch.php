<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseProxy;

class TypesensePartitionedSearch
{
    /**
     * Create a sort function for a typesense sorting spec.
     * For example:"person.person:asc,@type:desc,objectType:desc".
     *
     * Not perfect, see:
     * https://typesense.org/docs/28.0/api/search.html#sorting-based-on-filter-score
     *
     * The sorting function takes the hit object as input (not the nested document)
     */
    public static function createSortFunction(string $sortSpec): callable
    {
        $sortFields = [];
        $parts = explode(',', $sortSpec);

        foreach ($parts as $part) {
            [$field, $direction] = explode(':', $part);
            $isAscending = strtolower($direction) === 'asc';
            $sortFields[] = [
                'field' => $field,
                'ascending' => $isAscending,
            ];
        }

        $getNestedValue = function ($obj, string $path) {
            if ($path === '_text_match') {
                $keys = ['text_match'];
            } else {
                $keys = explode('.', 'document.'.$path);
            }
            $value = $obj;
            foreach ($keys as $key) {
                $value = $value->$key ?? null;
            }

            return $value;
        };

        return function ($a, $b) use ($sortFields, $getNestedValue) {
            foreach ($sortFields as $sort) {
                $field = $sort['field'];
                $ascending = $sort['ascending'];

                $valueA = $getNestedValue($a, $field);
                $valueB = $getNestedValue($b, $field);

                if ($valueA === $valueB) {
                    continue;
                }

                if (is_string($valueA) && is_string($valueB)) {
                    $result = strcmp($valueA, $valueB);
                } else {
                    $result = ($valueA < $valueB) ? -1 : 1;
                }

                return $ascending ? $result : -$result;
            }

            return 0;
        };
    }

    public static function sortFacetCounts(&$facetCounts): void
    {
        $sortCounts = function ($a, $b) {
            if ($a->count !== $b->count) {
                return $b->count - $a->count;
            }

            return strcmp($a->value, $b->value);
        };
        usort($facetCounts, $sortCounts);
    }

    /**
     * Merges two facet_counts->counts parts of a result.
     */
    public static function mergeCounts(object $counts1, object $counts2): object
    {
        if ($counts1->field_name !== $counts2->field_name) {
            throw new \RuntimeException();
        }
        $newCounts = new \stdClass();
        $newCounts->field_name = $counts1->field_name;
        $newCounts->sampled = $counts1->sampled || $counts2->sampled;

        $newCounts->counts = $counts1->counts;
        $toAdd = [];
        foreach ($counts2->counts as $count2) {
            $found = false;
            foreach ($newCounts->counts as $count1) {
                if ($count1->value === $count2->value) {
                    $found = true;
                    $count1->count += $count2->count;
                    break;
                }
            }
            if (!$found) {
                $toAdd[] = $count2;
            }
        }

        $newCounts->counts = array_merge($newCounts->counts, $toAdd);
        assert(is_array($newCounts->counts));
        // Not strictly needed here, but sort for to make testing easier
        self::sortFacetCounts($newCounts->counts);

        $newCounts->stats = new \stdClass();
        // We can't know the real value, so just compute a lower limit
        $newCounts->stats->total_values = max($counts1->stats->total_values, $counts2->stats->total_values, count($newCounts->counts));

        return $newCounts;
    }

    /**
     * Merges two facet_counts parts of a result.
     *
     * @param object[] $facetCounts1
     * @param object[] $facetCounts2
     *
     * @return object[]
     */
    public static function mergeFaceCounts(array $facetCounts1, array $facetCounts2): array
    {
        $newFacetCounts = $facetCounts1;
        $toAdd = [];
        foreach ($facetCounts2 as $count2) {
            $found = false;
            foreach ($newFacetCounts as &$count) {
                if ($count->field_name === $count2->field_name) {
                    $count = self::mergeCounts($count, $count2);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $toAdd[] = $count2;
            }
        }
        $newFacetCounts = array_merge($newFacetCounts, $toAdd);

        return $newFacetCounts;
    }

    /**
     * Merges two results.
     */
    public static function mergeResults(object $result1, object $result2): object
    {
        if (isset($result1->error)) {
            return $result1;
        } elseif (isset($result2->error)) {
            return $result2;
        }
        $newResult = new \stdClass();
        $newResult->facet_counts = self::mergeFaceCounts($result1->facet_counts, $result2->facet_counts);
        $newResult->found = $result1->found + $result2->found;
        if ($result1->request_params->collection_name === $result2->request_params->collection_name) {
            $newResult->out_of = $result1->out_of;
        } else {
            $newResult->out_of = $result1->out_of + $result2->out_of;
        }
        $newResult->page = $result1->page;
        $newResult->request_params = $result1->request_params;
        $newResult->search_cutoff = $result1->search_cutoff || $result2->search_cutoff;
        $newResult->search_time_ms = max($result1->search_time_ms, $result2->search_time_ms);
        // only for ungrouped searches
        if (isset($result1->hits)) {
            $newResult->hits = array_merge($result1->hits, $result2->hits);
        }
        // only for grouped searches
        if (isset($result1->grouped_hits)) {
            $newResult->grouped_hits = array_merge($result1->grouped_hits, $result2->grouped_hits);
        }
        if (isset($result1->found_docs)) {
            $newResult->found_docs = $result1->found_docs + $result2->found_docs;
        }

        return $newResult;
    }

    /**
     * Returns typesense range queries for partitioning based ona key that goes from 0 to $totalPartitions - 1.
     */
    public static function getPartitionsFilter(string $partitionKey, int $totalPartitions, int $numPartitions): array
    {
        if ($totalPartitions < $numPartitions || $totalPartitions < 1 || $numPartitions < 1) {
            throw new \RuntimeException('Invalid partitioning');
        }
        $perPartition = ceil($totalPartitions / $numPartitions);

        $partitions = [];
        for ($i = 0; $i < $numPartitions; ++$i) {
            $rangeStart = $i * $perPartition;
            $rangeEnd = min($rangeStart + $perPartition - 1, $totalPartitions - 1);

            $partitions[] = ['filter_by' => "$partitionKey: [$rangeStart..$rangeEnd]"];
        }

        return $partitions;
    }

    /**
     * Merges one or more partitioned search responses into one.
     */
    public static function mergeJsonResponses(string $request, array $jsonResponses, int $numPartitions): string
    {
        if ($numPartitions === 1) {
            return $jsonResponses[0];
        }
        if (count($jsonResponses) === 0) {
            throw new \RuntimeException('No results found');
        }

        /* After everything is merged, get the result in an expected shape. Limit lengths, sort etc. */
        $adjustResult = function ($search, &$result) {
            if (isset($result->error)) {
                return;
            }

            $page = $search->page ?? 1;
            // Defaults to 10 according to typesense docs
            $pageSize = $search->per_page ?? 10;
            // XXX: Default is '_text_match:desc,default_sorting_field:desc' but don't know 'default_sorting_field' here
            $sortBy = $search->sort_by ?? '_text_match:desc';
            // Defaults to 10 according to typesense docs
            $maxFacetValues = $search->max_facet_values ?? 10;

            // Sort facet counts and limit amount
            foreach ($result->facet_counts as &$facetCount) {
                self::sortFacetCounts($facetCount->counts);
                array_splice($facetCount->counts, $maxFacetValues);
            }

            if (isset($search->per_page)) {
                $result->request_params->per_page = $search->per_page;
            }

            // Sort hits and slice for pagination
            if (isset($result->grouped_hits)) {
                $sortFunction = self::createSortFunction($sortBy);
                usort($result->grouped_hits, fn ($a, $b) => $sortFunction($a->hits[0], $b->hits[0]));
                $result->grouped_hits = array_slice($result->grouped_hits, ($page - 1) * $pageSize, $pageSize);
            } else {
                $sortFunction = self::createSortFunction($sortBy);
                usort($result->hits, $sortFunction);
                $result->hits = array_slice($result->hits, ($page - 1) * $pageSize, $pageSize);
            }
        };

        $requestObj = json_decode($request, flags: JSON_THROW_ON_ERROR);
        $wasMulti = is_array($requestObj->searches ?? null);

        $responseObjects = [];
        foreach ($jsonResponses as $response) {
            $responseObjects[] = json_decode($response, flags: JSON_THROW_ON_ERROR);
        }

        $newResponse = new \stdClass();
        $mergedResults = [];
        $searchCount = count($responseObjects[0]->results);
        for ($i = 0; $i < $searchCount; ++$i) {
            $newResult = null;
            foreach ($responseObjects as $d) {
                $result = $d->results[$i];
                if ($newResult === null) {
                    $newResult = $result;
                } else {
                    $newResult = self::mergeResults($newResult, $result);
                }
            }

            if ($wasMulti) {
                $search = $requestObj->searches[$i];
            } else {
                $search = $requestObj;
            }
            $adjustResult($search, $newResult);

            $mergedResults[] = $newResult;
        }
        $newResponse->results = $mergedResults;

        // If it was a non-multi search, convert it back
        if (!$wasMulti) {
            if (count($mergedResults) !== 1) {
                throw new \RuntimeException('Unexpected results count for a non-multi search');
            }
            $newResponse = $mergedResults[0];
        }

        return json_encode($newResponse);
    }

    /**
     * Splits a typesense search request into 1 or more partitioned requests.
     * The responses of those requests can be merged again using mergeJsonResponses().
     *
     * Splitting results in requests being processed in a more parallel way, but have certain downsides atm:
     *   * The total_values of facets are no longer correct, they are just a lower bound (not possible)
     *   * Special sorting of facet values is not supported (could be improved)
     *   * The hits sorting only supports some parts of the typesense syntax (could be improved)
     *   * The hits are limited to 250 * partitions entries (could be improved)
     */
    public static function splitJsonRequest(string $request, int $numPartitions, bool $sameCollection = true): array
    {
        $adjustSearchForPartition = function (&$search, int $index) use ($numPartitions, $sameCollection) {
            if ($sameCollection) {
                $partitions = self::getPartitionsFilter('partitionKey', 100, $numPartitions);
                $partition = $partitions[$index];
                if (isset($search->filter_by) && trim($search->filter_by) !== '') {
                    $search->filter_by .= ' && '.$partition['filter_by'];
                } else {
                    $search->filter_by = $partition['filter_by'];
                }
            } else {
                $alias = $search->collection;
                if ($index > 0) {
                    $alias .= '-'.$index;
                }
                $search->collection = $alias;
            }

            // facet only searches don't require responses
            if (!isset($search->per_page) || $search->per_page > 0) {
                // fetch as much as we can, so we can emulate pagination for a few pages when merging
                // XXX: For some reason if the collection is empty 250 leads to an error (maybe a typesense bug?)
                // reducing to 249 makes it work again
                $search->per_page = 249;
                $search->page = 1;
            }
        };

        if ($numPartitions === 1) {
            return [$request];
        }

        $newRequestObjects = [];
        for ($i = 0; $i < $numPartitions; ++$i) {
            $requestObj = json_decode($request, flags: JSON_THROW_ON_ERROR);
            $isMulti = is_array($requestObj->searches ?? null);
            // Convert everything to a multi search, to simplify things
            if (!$isMulti) {
                $newMulti = (object) [];
                $newMulti->searches = [$requestObj];
                $requestObj = $newMulti;
            }
            foreach ($requestObj->searches as &$search) {
                $adjustSearchForPartition($search, $i);
            }
            $newRequestObjects[] = $requestObj;
        }

        return array_map(fn ($item) => json_encode($item), $newRequestObjects);
    }
}
