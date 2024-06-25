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

    public function getTypesenseBaseUrl(): string
    {
        return $this->config['typesense_base_url'];
    }

    public function getTypesenseApiKey(): string
    {
        return $this->config['typesense_api_key'];
    }
}
