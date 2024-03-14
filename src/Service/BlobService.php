<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Service;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CabinetBundle\Entity\DeliveryStatusChange;
use Dbp\Relay\CabinetBundle\Entity\Request;
use Dbp\Relay\CabinetBundle\Entity\RequestFile;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BlobService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var mixed
     */
    private $blobKey;
    /**
     * @var mixed
     */
    private $blobBucketId;

    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * @var string
     */
    private $blobBaseUrl;

    /**
     * @var BlobApi
     */
    private $blobApi;

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
        $this->blobBaseUrl = '';
        $this->blobKey = '';
        $this->blobBucketId = '';
    }

    public function setConfig(array $config)
    {
        $this->blobBaseUrl = $config['blob_base_url'] ?? '';
        $this->blobKey = $config['blob_key'] ?? '';
        $this->blobBucketId = $config['blob_bucket_id'] ?? '';
        $this->blobApi = new BlobApi($this->blobBaseUrl, $this->blobBucketId, $this->blobKey);
    }

    public function deleteBlobFileByRequestFile(RequestFile $requestFile): void
    {
        $blobIdentifier = $requestFile->getFileStorageIdentifier();

        try {
            $this->blobApi->deleteFileByIdentifier($blobIdentifier);
        } catch (BlobApiError $e) {
            $requestFileIdentifier = $requestFile->getIdentifier();
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFile could not be deleted from Blob!', 'cabinet:request-file-blob-delete-error', ['request-file-identifier' => $requestFileIdentifier, 'message' => $e->getMessage()]);
        }
    }

    public function deleteBlobFileByDeliveryStatusChange(DeliveryStatusChange $statusChange): void
    {
        $blobIdentifier = $statusChange->getFileStorageIdentifier();

        try {
            $this->blobApi->deleteFileByIdentifier($blobIdentifier);
        } catch (BlobApiError $e) {
            $identifier = $statusChange->getIdentifier();
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'DeliveryStatusChange file could not be deleted from Blob!', 'cabinet:delivery-status-change-file-blob-delete-error', ['delivery-status-change-identifier' => $identifier, 'message' => $e->getMessage()]);
        }
    }

    public function deleteBlobFilesByRequest(Request $request): void
    {
        $cabinetRequestIdentifier = $request->getIdentifier();

        try {
            // This will delete blob files for request files and delivery status changes
            $this->blobApi->deleteFilesByPrefix($this->getBlobPrefix($cabinetRequestIdentifier));
        } catch (BlobApiError $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFiles could not be deleted from Blob!', 'cabinet:request-file-blob-delete-error', ['request-identifier' => $cabinetRequestIdentifier, 'message' => $e->getMessage()]);
        }
    }

    public function downloadRequestFileAsContentUrl(RequestFile $requestFile): string
    {
        $blobIdentifier = $requestFile->getFileStorageIdentifier();

        try {
            $contentUrl = $this->blobApi->downloadFileAsContentUrlByIdentifier($blobIdentifier);
        } catch (BlobApiError $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFile could not be downloaded from Blob!', 'cabinet:request-file-blob-download-error', ['message' => $e->getMessage()]);
        }

        return $contentUrl;
    }

    public function downloadDeliveryStatusChangeFileAsContentUrl(DeliveryStatusChange $deliveryStatusChange): string
    {
        $blobIdentifier = $deliveryStatusChange->getFileStorageIdentifier();

        try {
            $contentUrl = $this->blobApi->downloadFileAsContentUrlByIdentifier($blobIdentifier);
        } catch (BlobApiError $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'File of the DeliveryStatusChange could not be downloaded from Blob!', 'cabinet:delivery-status-change-blob-download-error', ['message' => $e->getMessage()]);
        }

        return $contentUrl;
    }

    public function uploadRequestFile(string $cabinetRequestIdentifier, string $fileName, string $fileData): string
    {
        try {
            $identifier = $this->blobApi->uploadFile($this->getRequestFileBlobPrefix($cabinetRequestIdentifier), $fileName, $fileData);
        } catch (BlobApiError $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'RequestFile could not be uploaded to Blob!', 'cabinet:request-file-blob-upload-error', ['message' => $e->getMessage()]);
        }

        // Return the blob file ID
        return $identifier;
    }

    /**
     * @throws \Exception
     */
    public function uploadDeliveryStatusChangeFile(string $cabinetRequestIdentifier, string $fileName, string $fileData): string
    {
        try {
            $identifier = $this->blobApi->uploadFile($this->getDeliveryStatusChangeBlobPrefix($cabinetRequestIdentifier), $fileName, $fileData);
        } catch (BlobApiError $e) {
            // We don't care a lot about what exception we're throwing here, because we will just
            // store the file in the database if there are any issues with the blob storage
            throw new \Exception($e->getMessage());
        }

        // Return the blob file ID
        return $identifier;
    }

    protected function getBlobPrefix(string $cabinetRequestIdentifier): string
    {
        return 'request-'.$cabinetRequestIdentifier;
    }

    protected function getRequestFileBlobPrefix(string $cabinetRequestIdentifier): string
    {
        return $this->getBlobPrefix($cabinetRequestIdentifier);
    }

    protected function getDeliveryStatusChangeBlobPrefix(string $cabinetRequestIdentifier): string
    {
        // In the end we decided to use the same method as for request files,
        // because we want to optimize for deleting requests
        return $this->getBlobPrefix($cabinetRequestIdentifier);
    }
}
