<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class SyncController extends AbstractController
{
    public function __construct(private AuthorizationService $auth, private TypesenseSync $typesenseSync)
    {
    }

    public function __invoke(Request $request): ?SyncPersonAction
    {
        $this->auth->checkCanUse();

        $personId = $request->query->get('person_id');

        $this->typesenseSync->syncOne($personId);

        return new SyncPersonAction();
    }
}
