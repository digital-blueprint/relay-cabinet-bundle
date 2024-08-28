<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Service;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class BlobService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private AuthorizationService $auth;

    private ConfigurationService $config;

    public function __construct(AuthorizationService $auth, ConfigurationService $config)
    {
        $this->auth = $auth;
        $this->config = $config;
    }

    private function getInternalBlobApi(): BlobApi
    {
        $config = $this->config;
        $blobApi = new BlobApi($config->getBlobApiUrlInternal(), $config->getBlobBucketId(), $config->getBlobBucketKey());
        $blobApi->setOAuth2Token($config->getBlobIdpUrl(), $config->getBlobIdpClientId(), $config->getBlobIdpClientSecret());

        return $blobApi;
    }

    public function checkConnection(): void
    {
        $blobApi = $this->getInternalBlobApi();
        $blobApi->getFileDataByPrefix(Uuid::v4()->toRfc4122(), 0);
    }

    public function uploadFile(string $filename, string $payload, ?string $type = null, ?string $metadata = null): string
    {
        $blobApi = $this->getInternalBlobApi();

        return $blobApi->uploadFile($this->config->getBlobBucketPrefix(), $filename, $payload, $metadata ?? '', $type ?? '');
    }

    public function deleteFile(string $id): void
    {
        $blobApi = $this->getInternalBlobApi();

        $blobApi->deleteFileByIdentifier($id);
    }

    public function getSignatureForGivenRequest(Request $request): Response
    {
        if (!$this->auth->isAuthenticated()) {
            throw new ApiError(Response::HTTP_UNAUTHORIZED, 'access denied');
        }

        // Do basic authorization checks for the provided bearer token
        // TODO: Check permissions
        $this->auth->checkCanUse();

        $config = $this->config;
        $method = 'POST';
        $creationTime = rawurlencode((new \DateTime())->format('c'));

        // get stuff from body
        $prefix = $request->query->get('prefix', '');
        $fileName = $request->query->get('fileName', '');
        $retentionDuration = $request->query->get('retentionDuration', '');
        $notifyEmail = $request->query->get('notifyEmail', '');
        $type = $request->query->get('type', '');

        $blobApi = new BlobApi($this->config->getBlobApiUrl(), $config->getBlobBucketId(), $config->getBlobBucketKey());

        try {
            $params = [
                'bucketIdentifier' => $config->getBlobBucketId(),
                'creationTime' => $creationTime,
                'fileName' => $fileName,
                'method' => $method,
                'notifyEmail' => $notifyEmail,
                'prefix' => $prefix,
                'retentionDuration' => $retentionDuration,
                'type' => $type,
            ];

            $responseUrl = $blobApi->getSignedBlobFilesUrl($params);

            return new Response($responseUrl, 200);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'The blob url could not be generated! Please check your parameters.', 'cabinet:cannot-generate-signed-blob-url');
        }
    }
}
