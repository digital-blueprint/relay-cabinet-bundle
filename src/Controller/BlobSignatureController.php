<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Controller;

use Dbp\Relay\CabinetBundle\Service\BlobService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BlobSignatureController extends AbstractController
{
    private $blobService;

    public function __construct(BlobService $blobService)
    {
        $this->blobService = $blobService;
    }

    public function __invoke(Request $request): Response
    {
        return $this->proxy($request);
    }

    #[Route(path: '/cabinet/signature', name: 'cabinet_blob_signature', requirements: ['path' => '.+'])]
    public function proxy(Request $request): Response
    {
        $method = $request->query->get('method');

        if ($method === 'POST') {
            return $this->blobService->getSignatureForGivenPostRequest($request);
        } elseif ($method === 'GET') {
            return $this->blobService->getSignatureForGivenGetRequest($request);
        } elseif ($method === 'DELETE') {
            return $this->blobService->getSignatureForGivenDeleteRequest($request);
        } elseif ($method === 'PATCH') {
            return $this->blobService->getSignatureForGivenPatchRequest($request);
        } else {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'The provided method parameter is not of value POST, GET, DELETE or PATCH.', 'cabinet:signature-invalid-method');
        }
    }
}
