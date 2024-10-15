<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BlobSignatureController extends AbstractController
{
    private $blobService;
    private AuthorizationService $auth;

    public function __construct(BlobService $blobService, AuthorizationService $auth)
    {
        $this->blobService = $blobService;
        $this->auth = $auth;
    }

    public function __invoke(Request $request): Response
    {
        return $this->proxy($request);
    }

    #[Route(path: '/cabinet/blob-urls', name: 'cabinet_blob_signature', requirements: ['path' => '.+'], methods: ['POST'])]
    public function proxy(Request $request): Response
    {
        if (!$this->auth->isAuthenticated()) {
            throw new ApiError(Response::HTTP_UNAUTHORIZED, 'access denied');
        }
        $this->auth->checkCanUse();

        $method = $request->query->get('method');

        if ($method === 'POST') {
            return $this->blobService->getSignatureForGivenPostRequest($request);
        } elseif ($method === 'GET') {
            return $this->blobService->getSignatureForGivenGetRequest($request);
        } elseif ($method === 'DOWNLOAD') {
            return $this->blobService->getSignatureForGivenDownloadRequest($request);
        } elseif ($method === 'DELETE') {
            return $this->blobService->getSignatureForGivenDeleteRequest($request);
        } elseif ($method === 'PATCH') {
            return $this->blobService->getSignatureForGivenPatchRequest($request);
        } else {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'The provided method parameter is not of value POST, GET, DELETE or PATCH.', 'cabinet:signature-invalid-method');
        }
    }
}
