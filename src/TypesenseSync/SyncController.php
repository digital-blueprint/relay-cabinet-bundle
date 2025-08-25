<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SyncController extends AbstractController
{
    public function __construct(private AuthorizationService $auth, private TypesenseSync $typesenseSync)
    {
    }

    public function __invoke(Request $request): ?SyncPersonAction
    {
        $this->auth->checkCanUse();

        $documentId = $request->query->get('documentId');
        if ($documentId !== null) {
            $personId = $this->typesenseSync->getPersonIdForDocumentId($documentId);
            if ($personId === null) {
                throw new NotFoundHttpException('document with id='.$documentId.' not found');
            }
            $this->typesenseSync->syncOne($personId);
        } else {
            throw new BadRequestHttpException('documentId required');
        }

        return new SyncPersonAction();
    }
}
