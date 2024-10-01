<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Service;

class ConfigurationService
{
    private array $config = [];

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getTypesenseApiUrl(): string
    {
        return $this->config['typesense']['api_url'];
    }

    public function getTypesenseApiKey(): string
    {
        return $this->config['typesense']['api_key'];
    }

    /**
     * Returns the typesense API key used when talking to typesense via the proxy. It is read-only, and is limited
     * to a specific collection and actions on that collection.
     */
    public function getTypesenseProxyApiKey(): string
    {
        return 'cabinet:proxy-key';
    }

    public function getBlobApiUrl(): string
    {
        return $this->config['blob']['api_url'];
    }

    public function getBlobApiUrlInternal(): string
    {
        return $this->config['blob']['api_url_internal'] ?? $this->getBlobApiUrl();
    }

    public function getBlobIdpUrl(): string
    {
        return $this->config['blob']['idp_url'];
    }

    public function getBlobIdpClientId(): string
    {
        return $this->config['blob']['idp_client_id'];
    }

    public function getBlobIdpClientSecret(): string
    {
        return $this->config['blob']['idp_client_secret'];
    }

    public function getBlobBucketId(): string
    {
        return $this->config['blob']['bucket_id'];
    }

    public function getBlobBucketKey(): string
    {
        return $this->config['blob']['bucket_key'];
    }

    public function getBlobBucketPrefix(): string
    {
        return 'document-';
    }
}
