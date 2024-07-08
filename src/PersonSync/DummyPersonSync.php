<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\PersonSync;

class DummyPersonSync implements PersonSyncInterface
{
    public function getPersons(array $ids, ?string $cursor = null): PersonSyncResultInterface
    {
        return new DummyPersonSyncResult();
    }

    public function getAllPersons(?string $cursor = null): PersonSyncResultInterface
    {
        return new DummyPersonSyncResult();
    }
}
