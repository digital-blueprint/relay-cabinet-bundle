<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Service;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
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
}
