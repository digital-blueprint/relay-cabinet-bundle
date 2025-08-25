<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

class SyncTask
{
    public function __construct(public bool $full = false, public ?string $personId = null)
    {
    }
}
