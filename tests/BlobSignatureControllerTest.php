<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Tests;

use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CabinetBundle\Blob\BlobService;
use Dbp\Relay\CabinetBundle\Blob\BlobSignatureController;
use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Dbp\Relay\CoreBundle\API\UserSessionInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class BlobSignatureControllerTest extends TestCase
{
    private BlobService&MockObject $blobService;
    private ConfigurationService $configurationService;
    private TestHandler $handler;
    private BlobSignatureController $controller;

    protected function setUp(): void
    {
        $this->blobService = $this->createMock(BlobService::class);
        $this->blobService->method('getBucketIdentifier')->willReturn('cabinet');
        $userSession = $this->createMock(UserSessionInterface::class);
        $userSession->method('isAuthenticated')->willReturn(true);
        $userSession->method('getUserIdentifier')->willReturn('jane.doe@example.com');
        $this->configurationService = new ConfigurationService();
        $this->configurationService->setConfig(['audit_logging' => true]);
        $this->handler = new TestHandler();
        $this->controller = new BlobSignatureController(
            $this->blobService,
            $this->createMock(AuthorizationService::class),
            $this->configurationService,
            $userSession,
            new Logger('audit', [$this->handler])
        );
    }

    /**
     * @return iterable<string, array{array<string, string>, array<string, mixed>}>
     */
    public static function writeMethodProvider(): iterable
    {
        yield 'POST' => [
            ['method' => 'POST', 'prefix' => 'documents', 'type' => 'application/pdf'],
            [
                'relay-cabinet-blob-bucket-id' => 'cabinet',
                'relay-cabinet-user-id' => 'jane.doe@example.com',
                'query-parameters' => [
                    'method' => 'POST',
                    'prefix' => 'documents',
                    'type' => 'application/pdf',
                ],
            ],
        ];
        yield 'PATCH' => [
            ['method' => 'PATCH', 'identifier' => '01981db0-8694-73ef-8e96-9d8c180ef6a7'],
            [
                'relay-cabinet-blob-bucket-id' => 'cabinet',
                'relay-cabinet-user-id' => 'jane.doe@example.com',
                'relay-cabinet-blob-id' => '01981db0-8694-73ef-8e96-9d8c180ef6a7',
                'query-parameters' => ['method' => 'PATCH'],
            ],
        ];
        yield 'DELETE' => [
            ['method' => 'DELETE', 'identifier' => '01981db0-8694-73ef-8e96-9d8c180ef6a7'],
            [
                'relay-cabinet-blob-bucket-id' => 'cabinet',
                'relay-cabinet-user-id' => 'jane.doe@example.com',
                'relay-cabinet-blob-id' => '01981db0-8694-73ef-8e96-9d8c180ef6a7',
                'query-parameters' => ['method' => 'DELETE'],
            ],
        ];
    }

    /**
     * @param array<string, string> $queryParameters
     * @param array<string, mixed>  $expectedContext
     */
    #[DataProvider('writeMethodProvider')]
    public function testLogsSignedWriteUrlIssuance(array $queryParameters, array $expectedContext): void
    {
        $signedUrl = 'https://blob.example/signed?secret=do-not-log';
        $this->blobService->expects($this->once())
            ->method('createSignedUrlForGivenQueryParameters')
            ->with($queryParameters)
            ->willReturn($signedUrl);

        $response = ($this->controller)($this->createRequest($queryParameters));

        $this->assertSame(['blobUrl' => $signedUrl], json_decode((string) $response->getContent(), true));
        $this->assertCount(1, $this->handler->getRecords());
        $record = $this->handler->getRecords()[0];
        $this->assertSame('Issued signed URL for Blob', $record->message);
        $this->assertSame($expectedContext, $record->context);
    }

    #[TestWith(['GET'])]
    #[TestWith(['DOWNLOAD'])]
    public function testLogsSignedReadUrlIssuance(string $method): void
    {
        $queryParameters = ['method' => $method, 'identifier' => '01981db0-8694-73ef-8e96-9d8c180ef6a7'];
        $this->blobService->method('createSignedUrlForGivenQueryParameters')->willReturn('https://blob.example/signed');

        ($this->controller)($this->createRequest($queryParameters));

        $this->assertCount(1, $this->handler->getRecords());
        $record = $this->handler->getRecords()[0];
        $this->assertSame('Issued signed URL for Blob', $record->message);
        $this->assertSame([
            'relay-cabinet-blob-bucket-id' => 'cabinet',
            'relay-cabinet-user-id' => 'jane.doe@example.com',
            'relay-cabinet-blob-id' => '01981db0-8694-73ef-8e96-9d8c180ef6a7',
            'query-parameters' => ['method' => $method],
        ], $record->context);
    }

    public function testDoesNotLogWhenSignedUrlCreationFails(): void
    {
        $queryParameters = ['method' => 'DELETE', 'identifier' => '01981db0-8694-73ef-8e96-9d8c180ef6a7'];
        $this->blobService->method('createSignedUrlForGivenQueryParameters')
            ->willThrowException(new \RuntimeException('Signing failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Signing failed');
        try {
            ($this->controller)($this->createRequest($queryParameters));
        } finally {
            $this->assertSame([], $this->handler->getRecords());
        }
    }

    public function testAuditLoggingIsOptIn(): void
    {
        $this->configurationService->setConfig(['audit_logging' => false]);
        $queryParameters = ['method' => 'DELETE', 'identifier' => '01981db0-8694-73ef-8e96-9d8c180ef6a7'];
        $this->blobService->method('createSignedUrlForGivenQueryParameters')->willReturn('https://blob.example/signed');

        ($this->controller)($this->createRequest($queryParameters));

        $this->assertSame([], $this->handler->getRecords());
    }

    /**
     * @param array<string, string> $queryParameters
     */
    private function createRequest(array $queryParameters): Request
    {
        return Request::create('/cabinet/blob-urls?'.http_build_query($queryParameters), 'POST');
    }
}
