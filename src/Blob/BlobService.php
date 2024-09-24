<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
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

    private ?BlobApi $internalBlobApi;

    public function __construct(AuthorizationService $auth, ConfigurationService $config)
    {
        $this->auth = $auth;
        $this->config = $config;
        $this->internalBlobApi = null;
    }

    private function getInternalBlobApi(): BlobApi
    {
        if ($this->internalBlobApi === null) {
            $config = $this->config;
            $blobApi = new BlobApi($config->getBlobApiUrlInternal(), $config->getBlobBucketId(), $config->getBlobBucketKey());
            $blobApi->setOAuth2Token($config->getBlobIdpUrl(), $config->getBlobIdpClientId(), $config->getBlobIdpClientSecret());
            $this->internalBlobApi = $blobApi;
        }

        return $this->internalBlobApi;
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

    public function getBucketId(): string
    {
        return $this->config->getBlobBucketId();
    }

    public function deleteFile(string $id): void
    {
        $blobApi = $this->getInternalBlobApi();

        $blobApi->deleteFileByIdentifier($id);
    }

    /**
     * Get all blob files without data as an iterable, decoded as an array.
     */
    public function getAllFiles(int $perPage = 1000): iterable
    {
        $blobApi = $this->getInternalBlobApi();
        $bucketPrefix = $this->config->getBlobBucketPrefix();
        $page = 1;
        while (true) {
            $entries = $blobApi->getFileDataByPrefix($bucketPrefix, 0, page: $page, perPage: $perPage)['hydra:member'];
            foreach ($entries as $entry) {
                yield $entry;
            }
            if (count($entries) < $perPage) {
                break;
            }
            ++$page;
        }
    }

    public function getFile(string $id): array
    {
        $blobApi = $this->getInternalBlobApi();

        return $blobApi->getFileDataByIdentifier($id, 0);
    }

    public function getSignatureForGivenPostRequest(Request $request): Response
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
        $type = $request->query->get('type', '');

        if (!$type) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'The parameter type has to be provided', 'cabinet:missing-type');
        }

        $blobApi = new BlobApi($this->config->getBlobApiUrl(), $config->getBlobBucketId(), $config->getBlobBucketKey());

        try {
            $params = [
                'bucketIdentifier' => $config->getBlobBucketId(),
                'creationTime' => $creationTime,
                'method' => $method,
                'prefix' => $prefix,
                'type' => $type,
            ];

            $responseUrl = $blobApi->getSignedBlobFilesUrl($params);

            return new Response($responseUrl, 200);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'The blob url could not be generated! Please check your parameters.', 'cabinet:cannot-generate-signed-blob-url');
        }
    }

    public function getSignatureForGivenGetRequest(Request $request): Response
    {
        if (!$this->auth->isAuthenticated()) {
            throw new ApiError(Response::HTTP_UNAUTHORIZED, 'access denied');
        }

        // Do basic authorization checks for the provided bearer token
        // TODO: Check permissions
        $this->auth->checkCanUse();

        $config = $this->config;
        $method = 'GET';
        $creationTime = rawurlencode((new \DateTime())->format('c'));

        // get stuff from body
        $includeData = $request->query->get('includeData', '');
        $id = $request->query->get('identifier', '');

        if ($includeData && $includeData !== '1') {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'The parameter includeData has to be 1 or not be provided at all.', 'cabinet:invalid-include-data');
        }

        if (!$id) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'The parameter identifier has to be provided.', 'cabinet:missing-identifier');
        }

        $blobApi = new BlobApi($this->config->getBlobApiUrl(), $config->getBlobBucketId(), $config->getBlobBucketKey());

        try {
            if ($includeData) {
                $params = [
                    'bucketIdentifier' => $config->getBlobBucketId(),
                    'creationTime' => $creationTime,
                    'includeData' => $includeData,
                    'method' => $method,
                ];
            } else {
                $params = [
                    'bucketIdentifier' => $config->getBlobBucketId(),
                    'creationTime' => $creationTime,
                    'method' => $method,
                ];
            }

            $responseUrl = $blobApi->getSignedBlobFilesUrl($params, $id);

            return new Response($responseUrl, 200);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'The blob url could not be generated! Please check your parameters.', 'cabinet:cannot-generate-signed-blob-url');
        }
    }

    public function getSignatureForGivenDownloadRequest(Request $request): Response
    {
        if (!$this->auth->isAuthenticated()) {
            throw new ApiError(Response::HTTP_UNAUTHORIZED, 'access denied');
        }

        // Do basic authorization checks for the provided bearer token
        // TODO: Check permissions
        $this->auth->checkCanUse();

        $config = $this->config;
        $method = 'GET';
        $creationTime = rawurlencode((new \DateTime())->format('c'));

        // get stuff from body
        $id = $request->query->get('identifier', '');

        if (!$id) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'The parameter identifier has to be provided.', 'cabinet:missing-identifier');
        }

        $blobApi = new BlobApi($this->config->getBlobApiUrl(), $config->getBlobBucketId(), $config->getBlobBucketKey());

        try {
            $params = [
                'bucketIdentifier' => $config->getBlobBucketId(),
                'creationTime' => $creationTime,
                'method' => $method,
            ];

            $responseUrl = $blobApi->getSignedBlobFilesUrl($params, $id);

            return new Response($responseUrl, 200);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'The blob url could not be generated! Please check your parameters.', 'cabinet:cannot-generate-signed-blob-url');
        }
    }

    public function getSignatureForGivenDeleteRequest(Request $request): Response
    {
        if (!$this->auth->isAuthenticated()) {
            throw new ApiError(Response::HTTP_UNAUTHORIZED, 'access denied');
        }

        // Do basic authorization checks for the provided bearer token
        // TODO: Check permissions
        $this->auth->checkCanUse();

        $config = $this->config;
        $method = 'DELETE';
        $creationTime = rawurlencode((new \DateTime())->format('c'));

        $id = $request->query->get('identifier', '');

        if (!$id) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'The parameter identifier has to be provided.', 'cabinet:missing-identifier');
        }

        $blobApi = new BlobApi($this->config->getBlobApiUrl(), $config->getBlobBucketId(), $config->getBlobBucketKey());

        try {
            $params = [
                'bucketIdentifier' => $config->getBlobBucketId(),
                'creationTime' => $creationTime,
                'method' => $method,
            ];

            $responseUrl = $blobApi->getSignedBlobFilesUrl($params, $id);

            return new Response($responseUrl, 200);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'The blob url could not be generated! Please check your parameters.', 'cabinet:cannot-generate-signed-blob-url');
        }
    }

    public function getSignatureForGivenPatchRequest(Request $request): Response
    {
        if (!$this->auth->isAuthenticated()) {
            throw new ApiError(Response::HTTP_UNAUTHORIZED, 'access denied');
        }

        // Do basic authorization checks for the provided bearer token
        // TODO: Check permissions
        $this->auth->checkCanUse();

        $config = $this->config;
        $method = 'PATCH';
        $creationTime = rawurlencode((new \DateTime())->format('c'));

        // get stuff from body
        $prefix = $request->query->get('prefix', '');
        $type = $request->query->get('type', '');
        $id = $request->query->get('identifier', '');

        if (!$id) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'The parameter identifier has to be provided.', 'cabinet:missing-identifier');
        }

        $blobApi = new BlobApi($this->config->getBlobApiUrl(), $config->getBlobBucketId(), $config->getBlobBucketKey());

        try {
            $params = [
                'bucketIdentifier' => $config->getBlobBucketId(),
                'creationTime' => $creationTime,
                'method' => $method,
            ];

            if ($prefix) {
                $params['prefix'] = $prefix;
            }
            if ($type) {
                $params['type'] = $type;
            }

            $responseUrl = $blobApi->getSignedBlobFilesUrl($params, $id);

            return new Response($responseUrl, 200);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'The blob url could not be generated! Please check your parameters.', 'cabinet:cannot-generate-signed-blob-url');
        }
    }
}
