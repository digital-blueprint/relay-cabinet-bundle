<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SyncController extends AbstractController
{
    public function __construct(private AuthorizationService $auth, private TypesenseSync $typesenseSync)
    {
    }

    public function __invoke(Request $request): ?SyncPersonAction
    {
        $this->auth->checkCanUse();

        $personId = $request->query->get('person_id');
        $documentId = $request->query->get('documentId');
        if ($documentId !== null) {
            $this->typesenseSync->syncOneByDocumentId($documentId);
        } elseif ($personId !== null) {
            $this->typesenseSync->syncOne($personId);
        } else {
            throw new BadRequestHttpException('one of person_id or documentId are required');
        }

        return new SyncPersonAction();
    }
}
