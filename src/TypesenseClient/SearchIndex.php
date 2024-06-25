<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseClient;

use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class SearchIndex implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ConfigurationService $config;
    private Connection $connection;

    public function __construct(ConfigurationService $config)
    {
        $this->config = $config;
        $this->connection = new Connection($config->getTypesenseBaseUrl(), $config->getTypesenseApiKey());
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->connection->setLogger($logger);
    }

    public function checkConnection(): void
    {
        $client = $this->connection->getClient();
        $client->getHealth()->retrieve();
        $client->getMultiSearch()->perform(['searches' => [['q' => 'healthcheck']]]);
    }
}
