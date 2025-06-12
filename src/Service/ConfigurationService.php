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

    public function getTypesenseSearchPartitions(): int
    {
        return $this->config['typesense']['search_partitions'];
    }

    public function getTypesenseSearchCacheTtl(): int
    {
        return $this->config['typesense']['search_cache_ttl'];
    }

    public function getBlobBucketId(): string
    {
        return $this->config['blob_library']['bucket_identifier'];
    }

    public function getBlobBucketPrefix(): string
    {
        return 'document-';
    }
}
