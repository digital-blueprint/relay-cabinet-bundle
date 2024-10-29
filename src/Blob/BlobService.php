<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use Dbp\Relay\BlobBundle\Api\FileApi;
use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class BlobService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private AuthorizationService $auth;

    private ConfigurationService $config;

    private ?BlobApi $internalBlobApi;
    private FileApi $fileApi;

    public function __construct(AuthorizationService $auth, ConfigurationService $config, FileApi $fileApi)
    {
        $this->auth = $auth;
        $this->config = $config;
        $this->internalBlobApi = null;
        $this->fileApi = $fileApi;
    }

    private function getInternalBlobApi(): BlobApi
    {
        if (!$this->config->getUseBlobApi()) {
            throw new \RuntimeException('Internal blob api is disabled');
        }

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
        if ($this->config->getUseBlobApi()) {
            $blobApi = $this->getInternalBlobApi();
            $blobApi->getFileDataByPrefix(Uuid::v4()->toRfc4122(), 0);
        }
    }

    public function getBlobApiUrl(): string
    {
        return $this->config->getBlobApiUrl();
    }

    public function uploadFile(string $filename, string $payload, ?string $type = null, ?string $metadata = null): string
    {
        if (!$this->config->getUseBlobApi()) {
            $filesystem = new Filesystem();
            $tempFile = $filesystem->tempnam(sys_get_temp_dir(), 'cabinet_');

            try {
                if (file_put_contents($tempFile, $payload) === false) {
                    throw new \RuntimeException();
                }
                $file = new File($tempFile, true);
                $fileData = new FileData();
                $fileData->setFilename($filename);
                $fileData->setFile($file);
                $fileData->setPrefix($this->config->getBlobBucketPrefix());
                $fileData->setType($type);
                $fileData->setMetadata($metadata ?? '');

                return $this->fileApi->addFile($fileData, $this->config->getBlobBucketId())->getIdentifier();
            } finally {
                @unlink($tempFile);
            }
        } else {
            $blobApi = $this->getInternalBlobApi();

            return $blobApi->uploadFile($this->config->getBlobBucketPrefix(), $filename, $payload, $metadata ?? '', $type ?? '');
        }
    }

    public function getBucketId(): string
    {
        return $this->config->getBlobBucketId();
    }

    public function deleteFile(string $id): void
    {
        if (!$this->config->getUseBlobApi()) {
            $this->fileApi->removeFile($id);
        } else {
            $blobApi = $this->getInternalBlobApi();
            $blobApi->deleteFileByIdentifier($id);
        }
    }

    /**
     * Get all blob files without data as an iterable, decoded as an array.
     */
    public function getAllFiles(int $perPage = 1000): iterable
    {
        if (!$this->config->getUseBlobApi()) {
            $bucketId = $this->config->getBlobBucketId();
            $bucketPrefix = $this->config->getBlobBucketPrefix();
            $page = 1;
            while (true) {
                $entries = $this->fileApi->getFiles($bucketId, [FileApi::PREFIX_OPTION => $bucketPrefix, FileApi::INCLUDE_DELETE_AT_OPTION => true], $page, $perPage);
                foreach ($entries as $entry) {
                    yield self::fileDataToJson($entry);
                }
                if (count($entries) === 0) {
                    break;
                }
                ++$page;
            }
        } else {
            $blobApi = $this->getInternalBlobApi();
            $bucketPrefix = $this->config->getBlobBucketPrefix();
            $page = 1;
            while (true) {
                $entries = $blobApi->getFileDataByPrefix($bucketPrefix, 0, page: $page, perPage: $perPage, includeDeleteAt: true)['hydra:member'];
                foreach ($entries as $entry) {
                    yield $entry;
                }
                if (count($entries) === 0) {
                    break;
                }
                ++$page;
            }
        }
    }

    private static function fileDataToJson(FileData $fileData): array
    {
        return [
            'identifier' => $fileData->getIdentifier(),
            'fileName' => $fileData->getFilename(),
            'mimeType' => $fileData->getMimeType(),
            'dateCreated' => $fileData->getDateCreated()->format(\DateTime::ATOM),
            'dateModified' => $fileData->getDateModified()->format(\DateTime::ATOM),
            'deleteAt' => $fileData->getDeleteAt()?->format(\DateTime::ATOM),
            'metadata' => $fileData->getMetadata(),
        ];
    }

    public function getFile(string $id): array
    {
        if (!$this->config->getUseBlobApi()) {
            $fileData = $this->fileApi->getFile($id, [FileApi::INCLUDE_DELETE_AT_OPTION => true]);

            return self::fileDataToJson($fileData);
        } else {
            $blobApi = $this->getInternalBlobApi();

            return $blobApi->getFileDataByIdentifier($id, 0, includeDeleteAt: true);
        }
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
                'includeDeleteAt' => '1',
            ];

            $responseUrl = $blobApi->getSignedBlobFilesUrl($params);

            return new Response($this->getJsonEncodedBlobUrl($responseUrl), 200);
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
                    'includeDeleteAt' => '1',
                ];
            } else {
                $params = [
                    'bucketIdentifier' => $config->getBlobBucketId(),
                    'creationTime' => $creationTime,
                    'method' => $method,
                    'includeDeleteAt' => '1',
                ];
            }

            $responseUrl = $blobApi->getSignedBlobFilesUrl($params, $id);

            return new Response($this->getJsonEncodedBlobUrl($responseUrl), 200);
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
                'includeDeleteAt' => '1',
            ];

            $responseUrl = $blobApi->getSignedBlobFilesUrl($params, $id, 'download');

            return new Response($this->getJsonEncodedBlobUrl($responseUrl), 200);
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
                'includeDeleteAt' => '1',
            ];

            $responseUrl = $blobApi->getSignedBlobFilesUrl($params, $id);

            return new Response($this->getJsonEncodedBlobUrl($responseUrl), 200);
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
                'includeDeleteAt' => '1',
            ];

            if ($prefix) {
                $params['prefix'] = $prefix;
            }
            if ($type) {
                $params['type'] = $type;
            }

            $responseUrl = $blobApi->getSignedBlobFilesUrl($params, $id);

            return new Response($this->getJsonEncodedBlobUrl($responseUrl), 200);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'The blob url could not be generated! Please check your parameters.', 'cabinet:cannot-generate-signed-blob-url');
        }
    }

    private function getJsonEncodedBlobUrl(string $blobUrl)
    {
        $payload = [
            'blobUrl' => $blobUrl,
        ];

        return json_encode($payload);
    }
}
