<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Dbp\Relay\CoreBundle\API\UserSessionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BlobSignatureController extends AbstractController
{
    public function __construct(
        private readonly BlobService $blobService,
        private readonly AuthorizationService $authorizationService,
        private readonly ConfigurationService $configurationService,
        private readonly UserSessionInterface $userSession,
        private readonly LoggerInterface $auditLogger,
        private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    /**
     * @throws BlobApiError
     */
    public function __invoke(Request $request): Response
    {
        $this->authorizationService->checkCanUse();

        $queryParameters = $request->query->all();
        unset($queryParameters['metadata']);
        $bodyParameters = $request->getPayload()->all();
        foreach ($bodyParameters as $key => $value) {
            if ($key !== 'metadata') {
                throw new HttpException(Response::HTTP_BAD_REQUEST, "The body parameter '$key' is not defined.");
            }
        }
        $queryParameters += $bodyParameters;
        $this->blobService->validateSignedUrlQueryParameters($queryParameters);

        try {
            $metadata = $queryParameters['metadata'] ?? null;
            $newMetadata = $metadata === null ? null : json_decode($metadata, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Metadata must contain valid JSON.', $exception);
        }
        $oldMetadata = null;
        $oldType = null;
        if ($queryParameters['method'] !== 'POST') {
            $blobFile = $this->blobService->getFile($queryParameters['identifier']);
            try {
                $metadata = $blobFile->getMetadata();
                $oldMetadata = $metadata === null ? null : json_decode($metadata, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Stored Blob metadata contains invalid JSON.', $exception);
            }
            $oldType = $blobFile->getType();
        }
        $event = new BlobAuthorizationEvent(
            $queryParameters['method'],
            $queryParameters['identifier'] ?? null,
            $oldMetadata,
            $newMetadata,
            $oldType,
            in_array($queryParameters['method'], ['POST', 'PATCH'], true)
                ? ($queryParameters['type'] ?? $oldType) : null
        );
        $this->eventDispatcher->dispatch($event);
        if (!$event->isAccessGranted()) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Access to the requested Blob operation is denied.');
        }

        $signedUrl = $this->blobService->createSignedUrlForGivenQueryParameters($queryParameters);

        if ($this->configurationService->isAuditLoggingEnabled()) {
            // The requested method is part of the logged query parameters
            $this->auditLogger->debug('Issued signed URL for Blob', $this->createAuditContext($queryParameters));
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
        unset($queryParameters['metadata']);
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
