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
     * Merges multiple pages from the same partition into one result.
     *
     * Unlike mergeResults() which merges across partitions (summing found/out_of),
     * this merges pages within a single partition: hits are concatenated but found/out_of
     * come only from the first page, since each page already reports the same total count.
     */
    public static function mergePages(object $page1, object $page2): object
    {
        if (isset($page1->error)) {
            return $page1;
        } elseif (isset($page2->error)) {
            return $page2;
        }
        $merged = clone $page1;
        if (isset($page1->hits)) {
            $merged->hits = array_merge($page1->hits, $page2->hits);
        }
        if (isset($page1->grouped_hits)) {
            $merged->grouped_hits = array_merge($page1->grouped_hits, $page2->grouped_hits);
        }

        return $merged;
    }

    /**
     * Merges one or more partitioned search responses into one.
     *
     * $nestedResponses is a nested array indexed as [partition][page], matching the structure
     * returned by splitJsonRequest(). Each element is an already-decoded JSON response string.
     */
    public static function mergeJsonResponses(string $request, array $nestedResponses, int $numPartitions): object
    {
        $pagesPerPartition = count($nestedResponses[0] ?? [[]]);
        if ($numPartitions * $pagesPerPartition === 1) {
            return $nestedResponses[0][0];
        }
        if (count($nestedResponses) === 0) {
            throw new \RuntimeException('No results found');
        }

        // If we fetch X from each partition, then we can only use the first X of the merged result
        $maxUsableResults = $pagesPerPartition * self::MAX_PER_PAGE;

        /* After everything is merged, get the result in an expected shape. Limit lengths, sort etc. */
        $adjustResult = function ($search, &$result) use ($maxUsableResults) {
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

            $result->page = $page;

            if (isset($search->per_page)) {
                $result->request_params->per_page = $search->per_page;
            }

            // Sort hits and slice for pagination
            if (isset($result->grouped_hits)) {
                $sortFunction = self::createSortFunction($sortBy);
                usort($result->grouped_hits, fn ($a, $b) => $sortFunction($a->hits[0], $b->hits[0]));
                $result->grouped_hits = array_slice($result->grouped_hits, 0, $maxUsableResults);
                $result->grouped_hits = array_slice($result->grouped_hits, ($page - 1) * $pageSize, $pageSize);
            } else {
                $sortFunction = self::createSortFunction($sortBy);
                usort($result->hits, $sortFunction);
                $result->hits = array_slice($result->hits, 0, $maxUsableResults);
                $result->hits = array_slice($result->hits, ($page - 1) * $pageSize, $pageSize);
            }
        };

        $requestObj = json_decode($request, flags: JSON_THROW_ON_ERROR);
        $wasMulti = is_array($requestObj->searches ?? null);

        // Decode all responses: [partition][page] => response object
        $decodedResponses = [];
        foreach ($nestedResponses as $partitionPages) {
            $decodedPages = [];
            foreach ($partitionPages as $response) {
                $decodedPages[] = json_decode($response, flags: JSON_THROW_ON_ERROR);
            }
            $decodedResponses[] = $decodedPages;
        }

        $newResponse = new \stdClass();
        $mergedResults = [];
        $searchCount = count($decodedResponses[0][0]->results);
        for ($i = 0; $i < $searchCount; ++$i) {
            // Step 1: merge all pages within each partition (hits concatenated, found kept from page 1)
            $partitionResults = [];
            foreach ($decodedResponses as $partitionPages) {
                $partitionResult = null;
                foreach ($partitionPages as $decodedPage) {
                    $result = $decodedPage->results[$i];
                    if ($partitionResult === null) {
                        $partitionResult = $result;
                    } else {
                        $partitionResult = self::mergePages($partitionResult, $result);
                    }
                }
                $partitionResults[] = $partitionResult;
            }

            // Step 2: merge across partitions (found/out_of are summed)
            $newResult = null;
            foreach ($partitionResults as $partitionResult) {
                if ($newResult === null) {
                    $newResult = $partitionResult;
                } else {
                    $newResult = self::mergeResults($newResult, $partitionResult);
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

        return $newResponse;
    }

    /**
     * Check if there are not enough results and we should try searching again with different settings.
     *
     * @return array{0: bool, 1: object[]}
     */
    public static function getRetryOverrides(string $request, object $mergedResponse): array
    {
        $requestObj = json_decode($request, flags: JSON_THROW_ON_ERROR);
        $isMulti = is_array($requestObj->searches ?? null);
        // Convert everything to a multi search, to simplify things
        if (!$isMulti) {
            $newMulti = (object) [];
            $newMulti->searches = [$requestObj];
            $requestObj = $newMulti;
        }

        $retryOverrides = [];
        $needsRetry = false;
        $i = 0;
        foreach ($requestObj->searches as $search) {
            $result = $mergedResponse->results[$i++];

            $found = $result->found;
            $dropTokensThreshold = $search->drop_tokens_threshold ?? 1;
            $typoTokensThreshold = $search->typo_tokens_threshold ?? 1;

            $retryParams = new \stdClass();
            if ($found < $dropTokensThreshold) {
                $needsRetry = true;
                $retryParams->drop_tokens_threshold = $dropTokensThreshold;
            }
            if ($found < $typoTokensThreshold) {
                $needsRetry = true;
                $retryParams->typo_tokens_threshold = $typoTokensThreshold;
            }
            $retryOverrides[] = $retryParams;
        }

        return [$needsRetry, $retryOverrides];
    }

    /**
     * Apply search setting overrides to partitioned requests. $retryOverrides come from getRetryOverrides()).
     * $nestedRequests is the nested array[partition][page] returned by splitJsonRequest().
     */
    public static function applyRetryOverrides(array $nestedRequests, array $retryOverrides): void
    {
        foreach ($nestedRequests as $partitionPages) {
            foreach ($partitionPages as $partitionRequest) {
                $searchIndex = 0;
                foreach ($partitionRequest->searches as &$search) {
                    $searchOverrides = $retryOverrides[$searchIndex++] ?? null;
                    foreach ($searchOverrides as $key => $value) {
                        $search->$key = $value;
                    }
                }
            }
        }
    }

    /**
     * The maximum number of results Typesense returns per page.
     * 249 instead of 250 due to a Typesense bug: per_page=250 causes an error when the collection is empty.
     */
    private const MAX_PER_PAGE = 249;

    /**
     * Default maximum pages to query, to limit the amount of pages that are fetch in one request.
     */
    private const MAX_PAGES = 4;

    /**
     * Splits a typesense search request into 1 or more partitioned requests.
     * The responses of those requests can be merged again using mergeJsonResponses().
     *
     * Splitting results in requests being processed in a more parallel way, but have certain downsides atm:
     *   * The total_values of facets are no longer correct, they are just a lower bound (not possible)
     *   * Special sorting of facet values is not supported (could be improved)
     *   * The hits sorting only supports some parts of the typesense syntax (could be improved)
     *
     * $maxResults controls how many hits the caller may need in total. When this exceeds what a single
     * partition page can supply (numPartitions * MAX_PER_PAGE), multiple pages are fetched per partition
     * so that the merged pool is large enough to satisfy the request.
     *
     * Returns a nested array indexed as [partition][page].
     * Pass this directly to mergeJsonResponses() after collecting the responses in the same shape.
     *
     * @return object[][]
     */
    public static function splitJsonRequest(string $request, int $numPartitions, bool $sameCollection = true, int $maxResults = self::MAX_PER_PAGE, int $maxPages = self::MAX_PAGES): array
    {
        $pagesPerPartition = min($maxPages, ceil($maxResults / self::MAX_PER_PAGE));

        $adjustSearchForPartition = function (&$search, int $partitionIndex, int $pageIndex) use ($numPartitions, $sameCollection) {
            if ($sameCollection) {
                $partitions = self::getPartitionsFilter('partitionKey', 100, $numPartitions);
                $partition = $partitions[$partitionIndex];
                if (isset($search->filter_by) && trim($search->filter_by) !== '') {
                    $search->filter_by .= ' && '.$partition['filter_by'];
                } else {
                    $search->filter_by = $partition['filter_by'];
                }
            } else {
                $alias = $search->collection;
                if ($partitionIndex > 0) {
                    $alias .= '-'.$partitionIndex;
                }
                $search->collection = $alias;
            }

            // facet only searches don't require responses
            if (!isset($search->per_page) || $search->per_page > 0) {
                // fetch as much as we can per page, so we can emulate pagination for many pages when merging
                // XXX: For some reason if the collection is empty 250 leads to an error (maybe a typesense bug?)
                // reducing to 249 makes it work again
                $search->per_page = self::MAX_PER_PAGE;
                $search->page = $pageIndex + 1;
            }

            // These are settings which change the search strategy in case there are no results. Since we make
            // requests on a subset of the data, not getting any hits doesn't mean we won't get hits from other
            // collections. To somewhat fix this we disable them, and in case there are not enough results after merging
            // we retry with them enabled. This way we at least get no random typo tolerance results in case there are
            // enough hits overall.
            $search->drop_tokens_threshold = 0;
            $search->typo_tokens_threshold = 0;
        };

        $newRequestObjects = [];
        for ($i = 0; $i < $numPartitions; ++$i) {
            $partitionPages = [];
            for ($p = 0; $p < $pagesPerPartition; ++$p) {
                $requestObj = json_decode($request, flags: JSON_THROW_ON_ERROR);
                $isMulti = is_array($requestObj->searches ?? null);
                // Convert everything to a multi search, to simplify things
                if (!$isMulti) {
                    $newMulti = (object) [];
                    $newMulti->searches = [$requestObj];
                    $requestObj = $newMulti;
                }
                foreach ($requestObj->searches as &$search) {
                    $adjustSearchForPartition($search, $i, $p);
                }
                $partitionPages[] = $requestObj;
            }
            $newRequestObjects[] = $partitionPages;
        }

        return $newRequestObjects;
    }

    /**
     * Determines the maximum number of results the client may need from the request.
     * This is used to decide how many Typesense pages to fetch per partition.
     */
    public static function getMaxResultsFromRequest(string $requestContent): int
    {
        $requestObj = json_decode($requestContent, flags: JSON_THROW_ON_ERROR);
        $searches = $requestObj->searches ?? [$requestObj];

        $maxResults = 0;
        foreach ($searches as $search) {
            $page = $search->page ?? 1;
            $perPage = $search->per_page ?? 10;
            // A per_page of 0 means facet-only: no hits needed
            if ($perPage > 0) {
                $maxResults = max($maxResults, $page * $perPage);
            }
        }

        return max($maxResults, 1);
    }
}
