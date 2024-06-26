<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\PersonSync;

class DummyPersonSync implements PersonSyncInterface
{
    public function getPerson(string $id): ?array
    {
        return null;
    }

    public function getAllPersons(?string $cursor = null): PersonSyncResultInterface
    {
        return new DummyPersonSyncResult();
    }
}
