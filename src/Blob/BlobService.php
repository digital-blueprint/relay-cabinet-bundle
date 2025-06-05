<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Api\BlobFile;
use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class BlobService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const REQUIRED_QUERY_PARAMETERS = [
        'POST' => ['method', 'prefix', 'type'],
        'GET' => ['method', 'identifier'],
        'DELETE' => ['method', 'identifier'],
        'PATCH' => ['method', 'identifier'],
        'DOWNLOAD' => ['method', 'identifier'],
    ];

    private const OPTIONAL_QUERY_PARAMETERS = [
        'POST' => ['includeDeleteAt', 'includeData', 'deleteIn'],
        'GET' => ['includeDeleteAt', 'includeData'],
        'DELETE' => ['includeDeleteAt'],
        'PATCH' => ['includeDeleteAt', 'includeData', 'prefix', 'type', 'deleteIn'],
        'DOWNLOAD' => ['includeDeleteAt'],
    ];

    private ?BlobApi $blobApi = null;

    public function __construct(
        private readonly ConfigurationService $configurationService,
        #[Autowire(service: 'service_container')]
        private readonly ContainerInterface $container)
    {
    }

    /**
     * @throws BlobApiError
     */
    public function setConfig(array $config): void
    {
        $this->blobApi = BlobApi::createFromConfig($config, $this->container);
    }

    /**
     * @throws BlobApiError
     */
    public function checkConnection(): void
    {
        $this->blobApi->getFiles(options: [BlobApi::PREFIX_OPTION => Uuid::v4()->toRfc4122()]);
    }

    /**
     * @throws BlobApiError
     */
    public function uploadFile(string $filename, string $payload, ?string $type = null, ?string $metadata = null): string
    {
        $blobFile = new BlobFile();
        $blobFile->setFilename($filename);
        $blobFile->setFile($payload);
        $blobFile->setPrefix($this->configurationService->getBlobBucketPrefix());
        $blobFile->setType($type);
        $blobFile->setMetadata($metadata ?? '');

        return $this->blobApi->addFile($blobFile)->getIdentifier();
    }

    public function getBucketIdentifier(): string
    {
        return $this->blobApi->getBucketIdentifier();
    }

    /**
     * @throws BlobApiError
     */
    public function deleteFile(string $id): void
    {
        $this->blobApi->removeFile($id);
    }

    /**
     * Get all blob files without data as iterable, decoded as an array.
     *
     * @return iterable<BlobFile>
     *
     * @throws BlobApiError
     */
    public function getAllFiles(int $perPage = 512): iterable
    {
        $bucketPrefix = $this->configurationService->getBlobBucketPrefix();

        foreach (Pagination::getAllResultsPageNumberBased(
            function (int $currentPageNumber, int $maxNumItemsPerPage) use ($bucketPrefix) {
                return $this->blobApi->getFiles($currentPageNumber, $maxNumItemsPerPage, [
                    BlobApi::PREFIX_OPTION => $bucketPrefix,
                    BlobApi::INCLUDE_DELETE_AT_OPTION => true]);
            }, $perPage) as $blobFile) {
            yield $blobFile;
        }
    }

    /**
     * @throws BlobApiError
     */
    public function getFile(string $id): BlobFile
    {
        return $this->blobApi->getFile($id, [BlobApi::INCLUDE_DELETE_AT_OPTION => true]);
    }

    /**
     * @throws BlobApiError
     */
    public function createSignedUrlForGivenQueryParameters(array $queryParameters): string
    {
        $method = $queryParameters['method'] ?? null;
        if ($method === null) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                "The required parameter 'method' is missing",
                'cabinet:blob-urls-required-parameter-missing');
        }
        if (false === in_array($method, array_keys(self::REQUIRED_QUERY_PARAMETERS), true)) {
            throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                "method '$method' is invalid.", 'cabinet:blob-urls-invalid-method');
        }

        if ($method !== 'POST') {
            $queryParameters['includeDeleteAt'] = '1';
        }

        $queryParametersToCheck = $queryParameters;
        foreach (self::REQUIRED_QUERY_PARAMETERS[$method] as $requiredQueryParameter) {
            if (false === isset($queryParametersToCheck[$requiredQueryParameter])) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    "The required parameter '$requiredQueryParameter' is missing",
                    'cabinet:blob-urls-required-parameter-missing');
            }
            unset($queryParametersToCheck[$requiredQueryParameter]);
        }

        foreach ($queryParametersToCheck as $key => $value) {
            if (false === in_array($key, self::OPTIONAL_QUERY_PARAMETERS[$method], true)) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    "The parameter '$key' is not defined for method '$method'",
                    'cabinet:blob-urls-invalid-parameter');
            }
        }

        $identifier = $queryParameters['identifier'] ?? null;
        unset($queryParameters['identifier']);
        unset($queryParameters['method']);

        $action = null;
        if ($method === 'DOWNLOAD') {
            $method = 'GET';
            $action = 'download';
        }

        return $this->blobApi->createSignedUrl($method, $queryParameters, [], $identifier, $action);
    }
}
