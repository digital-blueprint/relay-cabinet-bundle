<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CoreBundle\API\UserSessionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BlobSignatureController extends AbstractController
{
    public function __construct(
        private readonly BlobService $blobService,
        private readonly AuthorizationService $authorizationService,
        private readonly UserSessionInterface $userSession,
        private readonly LoggerInterface $auditLogger)
    {
    }

    /**
     * @throws BlobApiError
     */
    public function __invoke(Request $request): Response
    {
        $this->authorizationService->checkCanUse();

        $queryParameters = $request->query->all();
        $signedUrl = $this->blobService->createSignedUrlForGivenQueryParameters($queryParameters);
        $method = $queryParameters['method'] ?? null;

        if (in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
            $this->auditLogger->debug('Issued signed URL for Blob write', $this->createAuditContext($queryParameters));
        }

        return new Response(json_encode([
            'blobUrl' => $signedUrl,
        ]), 200);
    }

    /**
     * @param array<string, mixed> $queryParameters
     *
     * @return array<string, mixed>
     */
    private function createAuditContext(array $queryParameters): array
    {
        $context = ['relay-cabinet-blob-bucket-id' => $this->blobService->getBucketIdentifier()];
        if ($this->userSession->isAuthenticated()) {
            $context['relay-cabinet-user-id'] = $this->userSession->getUserIdentifier();
        }
        if (isset($queryParameters['identifier'])) {
            $context['relay-cabinet-blob-id'] = $queryParameters['identifier'];
            unset($queryParameters['identifier']);
        }
        $context['query-parameters'] = $queryParameters;

        return $context;
    }
}
