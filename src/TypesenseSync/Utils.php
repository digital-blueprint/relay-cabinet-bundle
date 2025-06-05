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
}
