<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseProxy;

class TypesensePartitionedSearch
{
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
        $newResult = new \stdClass();
        $newResult->facet_counts = self::mergeFaceCounts($result1->facet_counts, $result2->facet_counts);
        $newResult->found = $result1->found + $result2->found;
        $newResult->hits = array_merge($result1->hits, $result2->hits);
        $newResult->out_of = $result1->out_of;
        $newResult->page = $result1->page;
        $newResult->request_params = $result1->request_params;
        $newResult->search_cutoff = $result1->search_cutoff || $result2->search_cutoff;
        $newResult->search_time_ms = $result1->search_time_ms + $result2->search_time_ms;

        return $newResult;
    }

    public static function mergeMultiSearchResponses(array $responses): object
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
            $mergedResults[] = $newResult;
        }
        $newResponse->results = $mergedResults;

        return $newResponse;
    }

    public static function mergeSearchResponses(array $responses): object
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

    public static function mergeJsonResponses(array $responses, bool $multi): string
    {
        $decodedResponses = [];
        foreach ($responses as $response) {
            $decodedResponses[] = json_decode($response, flags: JSON_THROW_ON_ERROR);
        }

        if ($multi) {
            return json_encode(self::mergeMultiSearchResponses($decodedResponses));
        } else {
            return json_encode(self::mergeSearchResponses($decodedResponses));
        }
    }

    public static function splitJsonRequest(string $request, bool $multi): array
    {
        return [$request];
    }
}
