<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

class Utils
{
    /**
     * Given the syntax "foo.bar" will retrieve $document["foo"]["bar"].
     */
    public static function getField(array $document, string $field): string|array|null
    {
        $current = $document;
        foreach (explode('.', $field) as $key) {
            $current = $current[$key] ?? null;
        }

        return $current;
    }

    public static function getPartitionIndex(int $numPartitions, int $value, int $totalPartitions): int
    {
        if ($numPartitions <= 0) {
            throw new \InvalidArgumentException('numPartitions must be > 0');
        }
        if ($value < 0 || $value > $totalPartitions - 1) {
            throw new \InvalidArgumentException('value out of range');
        }

        $totalValues = $totalPartitions;
        $valuesPerPartition = $totalValues / $numPartitions;
        $partition = (int) floor($value / $valuesPerPartition);
        if ($partition >= $numPartitions) {
            $partition = $numPartitions - 1;
        }

        return $partition;
    }
}
