<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Tests\DualDeliveryApi;

use Dbp\Relay\CabinetBundle\DualDeliveryApi\DualDeliveryClient;

trait BaseSoapTrait
{
    /**
     * @return DualDeliveryClient
     */
    private function getMockService(string $response)
    {
        $soapClientMock = $this->getMockBuilder(DualDeliveryClient::class)
            ->setConstructorArgs(['nope', null, true])
            ->onlyMethods(['__doRequest'])
            ->getMock();
        $soapClientMock->method('__doRequest')->will($this->returnValue($response));

        return $soapClientMock;
    }
}
