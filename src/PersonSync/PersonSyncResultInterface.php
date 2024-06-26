<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\PersonSync;

interface PersonSyncResultInterface
{
    /**
     * Returns an array of person result items.
     */
    public function getPersons(): array;

    /**
     * An opaque string which can be used to continue syncing.
     */
    public function getCursor(): string;
}
