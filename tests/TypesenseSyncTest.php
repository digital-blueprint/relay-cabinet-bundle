<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Tests;

use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Dbp\Relay\CabinetBundle\TypesenseSync\TypesenseClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TypesenseSyncTest extends TestCase
{
    public function testCheckConnection(): void
    {
        $config = new ConfigurationService();
        $config->setConfig([
            'typesense' => [
                'api_url' => '',
                'api_key' => 'bla',
            ],
        ]);
        $client = new TypesenseClient($config);
        $mockClient = new MockHttpClient([
            new MockResponse('{"ok":true}', ['http_code' => 200]),
            new MockResponse('{"results":[]}', ['http_code' => 200]),
        ]);
        $client->setHttpClient($mockClient);
        $client->checkConnection();
        $this->assertSame(2, $mockClient->getRequestsCount());
    }
}
