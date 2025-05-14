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
        $newCounts->stats = new \stdClass();
        $newCounts->stats->total_values = count($newCounts->counts);

        return $newCounts;
    }

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
        $newResult->out_of = $result1->out_of;
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

    public static function mergeMultiSearchResponses(object $requestObj, array $responses): object
    {
        $newResponse = new \stdClass();
        $mergedResults = [];
        $searchCount = count($responses[0]->results);
        for ($i = 0; $i < $searchCount; ++$i) {
            $newResult = null;
            foreach ($responses as $d) {
                $result = $d->results[$i];
                if ($newResult === null) {
                    $newResult = $result;
                } else {
                    $newResult = self::mergeResults($newResult, $result);
                }
            }

            if (!isset($newResult->error)) {
                $search = $requestObj->searches[$i];
                $page = $search->page ?? 1;
                // Defaults to 10 according to typesense docs
                $pageSize = $search->per_page ?? 10;

                // XXX: Default is '_text_match:desc,default_sorting_field:desc' but don't know 'default_sorting_field' here
                $sortBy = $search->sort_by ?? '_text_match:desc';

                // Sort and slice for pagination
                if (isset($newResult->grouped_hits)) {
                    $sortFunction = self::createSortFunction($sortBy);
                    usort($newResult->grouped_hits, fn ($a, $b) => $sortFunction($a->hits[0], $b->hits[0]));
                    $newResult->grouped_hits = array_slice($newResult->grouped_hits, ($page - 1) * $pageSize, $pageSize);
                } else {
                    $sortFunction = self::createSortFunction($sortBy);
                    usort($newResult->hits, $sortFunction);
                    $newResult->hits = array_slice($newResult->hits, ($page - 1) * $pageSize, $pageSize);
                }
            }

            $mergedResults[] = $newResult;
        }
        $newResponse->results = $mergedResults;

        return $newResponse;
    }

    public static function mergeSearchResponses(object $requestObj, array $responses): object
    {
        $newResponse = null;
        foreach ($responses as $response) {
            if ($newResponse === null) {
                $newResponse = $response;
            } else {
                $newResponse = self::mergeResults($newResponse, $response);
            }
        }

        return $newResponse;
    }

    public static function mergeJsonResponses(string $request, array $responses): string
    {
        if (count($responses) === 1) {
            return $responses[0];
        }

        $requestObj = json_decode($request, flags: JSON_THROW_ON_ERROR);
        $isMulti = is_array($requestObj->searches ?? null);

        $responseObjects = [];
        foreach ($responses as $response) {
            $responseObjects[] = json_decode($response, flags: JSON_THROW_ON_ERROR);
        }

        if ($isMulti) {
            return json_encode(self::mergeMultiSearchResponses($requestObj, $responseObjects));
        } else {
            return json_encode(self::mergeSearchResponses($requestObj, $responseObjects));
        }
    }

    public static function getPartitions(string $partitionKey, int $totalPartitions, int $numPartitions): array
    {
        $totalPartitions = max(1, $totalPartitions);
        $numPartitions = max(1, min($numPartitions, $totalPartitions));
        $perPartition = ceil($totalPartitions / $numPartitions);

        $partitions = [];
        for ($i = 0; $i < $numPartitions; ++$i) {
            $rangeStart = $i * $perPartition;
            $rangeEnd = min($rangeStart + $perPartition - 1, $totalPartitions - 1);

            $partitions[] = "$partitionKey: [$rangeStart..$rangeEnd]";
        }

        return $partitions;
    }

    public static function splitJsonRequest(string $request, int $numPartitions): array
    {
        $adjustSearch = function (&$search, $partition) {
            if ($search->filter_by) {
                $search->filter_by .= ' && '.$partition;
            } else {
                $search = $partition;
            }
            // facet only searches don't require responses
            if ($search->per_page > 0) {
                // fetch as much as we can, so we can emulate pagination for a few pages when merging
                $search->per_page = 250;
                $search->page = 1;
            }
        };

        $partitions = self::getPartitions('person.partitionKey', 100, $numPartitions);
        $newRequestObjects = [];
        foreach ($partitions as $partition) {
            $requestObj = json_decode($request, flags: JSON_THROW_ON_ERROR);
            $isMulti = is_array($requestObj->searches ?? null);
            if ($isMulti) {
                foreach ($requestObj->searches as &$search) {
                    $adjustSearch($search, $partition);
                }
            } else {
                $adjustSearch($requestObj, $partition);
            }
            $newRequestObjects[] = $requestObj;
        }

        return array_map(fn ($item) => json_encode($item), $newRequestObjects);
    }
}
