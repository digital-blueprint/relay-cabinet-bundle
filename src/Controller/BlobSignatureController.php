<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Controller;

use Dbp\Relay\CabinetBundle\Service\BlobService;
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

    #[Route(path: '/cabinet/signature', name: 'blob_signature', requirements: ['path' => '.+'])]
    public function proxy(Request $request): Response
    {
        return $this->blobService->getSignatureForGivenRequest($request);
    }
}
