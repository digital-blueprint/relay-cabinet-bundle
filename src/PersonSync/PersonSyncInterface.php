<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\PersonSync;

interface PersonSyncInterface
{
    /**
     * Returns a result containing all the requested person items. If an ID isn't found
     * the result will be missing.
     *
     * @param string[] $ids
     */
    public function getPersons(array $ids, ?string $cursor = null): PersonSyncResultInterface;

    /**
     * Returns a result containing all person items. Passing a cursor string
     * retrieved from a previous call might result in only new items being
     * returned since the last sync, but can also return all items if not possible
     * otherwise.
     *
     * On repeated calls there is no guarantee that at some point no results
     * will be returned.
     *
     * There is no functionality for detecting deleted items since the last sync.
     * For this a new sync has to be started by passing a null cursor.
     */
    public function getAllPersons(?string $cursor = null): PersonSyncResultInterface;
}
