<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\PersonSync;

class DummyPersonSyncResult implements PersonSyncResultInterface
{
    public function getPersons(): array
    {
        return [];
    }

    public function getCursor(): string
    {
        return '';
    }

    public function isFullSyncResult(): bool
    {
        return true;
    }
}
