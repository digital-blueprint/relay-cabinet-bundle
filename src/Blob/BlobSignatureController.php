<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BlobSignatureController extends AbstractController
{
    public function __construct(
        private readonly BlobService $blobService,
        private readonly AuthorizationService $authorizationService)
    {
    }

    /**
     * @throws BlobApiError
     */
    public function __invoke(Request $request): Response
    {
        $this->authorizationService->checkCanUse();

        return new Response(json_encode([
            'blobUrl' => $this->blobService->createSignedUrlForGivenQueryParameters($request->query->all()),
        ]), 200);
    }
}
