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

    /**
     * @var AuthorizationService
     */
    private $auth;

    public function __construct(UrlGeneratorInterface $router, AuthorizationService $auth)
    {
        $this->router = $router;
        $this->blobBaseUrl = '';
        $this->blobKey = '';
        $this->blobBucketId = '';
        $this->auth = $auth;
    }

    public function setConfig(array $config)
    {
        $this->blobBaseUrl = $config['blob_base_url'] ?? '';
        $this->blobKey = $config['blob_key'] ?? '';
        $this->blobBucketId = $config['blob_bucket_id'] ?? '';
        $this->blobApi = new BlobApi($this->blobBaseUrl, $this->blobBucketId, $this->blobKey);
    }

    public function getSignatureForGivenRequest(Request $request): Response
    {
        if (!$this->auth->isAuthenticated()) {
            throw new ApiError(Response::HTTP_UNAUTHORIZED, 'access denied');
        }

        // Do basic authorization checks for the provided bearer token
        // TODO: Check permissions
        $this->auth->checkCanUse();

        $method = 'POST';
        $creationTime = rawurlencode((new \DateTime())->format('c'));

        // get stuff from body
        $prefix = $request->query->get('prefix', '');
        $fileName = $request->query->get('fileName', '');
        $retentionDuration = $request->query->get('retentionDuration', '');
        $notifyEmail = $request->query->get('notifyEmail', '');
        $type = $request->query->get('type', '');

        try {
            $params = [
                'bucketIdentifier' => $this->blobBucketId,
                'creationTime' => $creationTime,
                'fileName' => $fileName,
                'method' => $method,
                'notifyEmail' => $notifyEmail,
                'prefix' => $prefix,
                'retentionDuration' => $retentionDuration,
                'type' => $type,
            ];

            $responseUrl = $this->blobApi->getSignedBlobFilesUrl($params);

            return new Response($responseUrl, 200);
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'The blob url could not be generated! Please check your parameters.', 'cabinet:cannot-generate-signed-blob-url');
        }
    }
}
