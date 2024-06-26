<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\PersonSync;

interface PersonSyncInterface
{
    /**
     * Returns an entry for a specific person. Or null if not found.
     */
    public function getPerson(string $id): ?array;

    /**
     * Returns a result containing all person items. Passing a cursor string
     * retrieved from a previous call will result in only new items being
     * returned since the last sync.
     *
     * On repeated calls there is no guarantee that at some point no results
     * will be returned.
     *
     * There is no functionality for detecting deleted items since the last sync.
     * For this a new sync has to be started.
     */
    public function getAllPersons(?string $cursor = null): PersonSyncResultInterface;
}
