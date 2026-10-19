<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Tests;

use Dbp\Relay\BlobLibrary\Api\BlobFile;
use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CabinetBundle\Blob\BlobAuthorizationEvent;
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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BlobSignatureControllerTest extends TestCase
{
    private BlobService&MockObject $blobService;
    private ConfigurationService $configurationService;
    private TestHandler $handler;
    private BlobSignatureController $controller;
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->blobService = $this->createMock(BlobService::class);
        $this->blobService->method('getBucketIdentifier')->willReturn('cabinet');
        $blobFile = new BlobFile();
        $blobFile->setMetadata('{"owner":"jane.doe@example.invalid"}');
        $blobFile->setType('existing-document');
        $this->blobService->method('getFile')->willReturn($blobFile);
        $this->eventDispatcher = new EventDispatcher();
        $userSession = $this->createMock(UserSessionInterface::class);
        $userSession->method('isAuthenticated')->willReturn(true);
        $userSession->method('getUserIdentifier')->willReturn('jane.doe@example.invalid');
        $this->configurationService = new ConfigurationService();
        $this->configurationService->setConfig(['audit_logging' => true]);
        $this->handler = new TestHandler();
        $this->controller = new BlobSignatureController(
            $this->blobService,
            $this->createMock(AuthorizationService::class),
            $this->configurationService,
            $userSession,
            new Logger('audit', [$this->handler]),
            $this->eventDispatcher
        );
    }

    /**
     * @return iterable<string, array{array<string, string>, array<string, mixed>}>
     */
    public static function writeMethodProvider(): iterable
    {
        yield 'POST' => [
            ['method' => 'POST', 'prefix' => 'documents', 'type' => 'application/pdf', 'metadata' => '{"private":"do-not-log"}'],
            [
                'relay-cabinet-blob-bucket-id' => 'cabinet',
                'relay-cabinet-user-id' => 'jane.doe@example.invalid',
                'query-parameters' => [
                    'method' => 'POST',
                    'prefix' => 'documents',
                    'type' => 'application/pdf',
                ],
            ],
        ];
        yield 'PATCH' => [
            ['method' => 'PATCH', 'identifier' => '01981db0-8694-73ef-8e96-9d8c180ef6a7', 'metadata' => '{"private":"do-not-log"}'],
            [
                'relay-cabinet-blob-bucket-id' => 'cabinet',
                'relay-cabinet-user-id' => 'jane.doe@example.invalid',
                'relay-cabinet-blob-id' => '01981db0-8694-73ef-8e96-9d8c180ef6a7',
                'query-parameters' => ['method' => 'PATCH'],
            ],
        ];
        yield 'DELETE' => [
            ['method' => 'DELETE', 'identifier' => '01981db0-8694-73ef-8e96-9d8c180ef6a7'],
            [
                'relay-cabinet-blob-bucket-id' => 'cabinet',
                'relay-cabinet-user-id' => 'jane.doe@example.invalid',
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
        $signedUrl = 'https://blob.invalid/signed?secret=do-not-log';
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
        $this->blobService->method('createSignedUrlForGivenQueryParameters')->willReturn('https://blob.invalid/signed');

        ($this->controller)($this->createRequest($queryParameters));

        $this->assertCount(1, $this->handler->getRecords());
        $record = $this->handler->getRecords()[0];
        $this->assertSame('Issued signed URL for Blob', $record->message);
        $this->assertSame([
            'relay-cabinet-blob-bucket-id' => 'cabinet',
            'relay-cabinet-user-id' => 'jane.doe@example.invalid',
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
        $this->blobService->method('createSignedUrlForGivenQueryParameters')->willReturn('https://blob.invalid/signed');

        ($this->controller)($this->createRequest($queryParameters));

        $this->assertSame([], $this->handler->getRecords());
    }

    public function testDispatchesAuthorizationBeforeSigning(): void
    {
        $queryParameters = ['method' => 'PATCH', 'identifier' => 'file-id', 'type' => 'new-document', 'metadata' => '{ "owner": "new-owner" }'];

        $authorized = false;
        $this->eventDispatcher->addListener(BlobAuthorizationEvent::class, function (BlobAuthorizationEvent $event) use (&$authorized): void {
            $this->assertSame('PATCH', $event->getMethod());
            $this->assertSame('file-id', $event->getIdentifier());
            $this->assertSame(['owner' => 'jane.doe@example.invalid'], $event->getOldMetadata());
            $this->assertSame(['owner' => 'new-owner'], $event->getNewMetadata());
            $this->assertSame('existing-document', $event->getOldType());
            $this->assertSame('new-document', $event->getNewType());
            $authorized = true;
        });
        $this->blobService->expects($this->once())->method('createSignedUrlForGivenQueryParameters')
            ->with($queryParameters)->willReturnCallback(function () use (&$authorized): string {
                $this->assertTrue($authorized);

                return 'https://blob.invalid/signed';
            });

        ($this->controller)($this->createRequest($queryParameters));
    }

    public function testDeniedAccessDoesNotSignOrLog(): void
    {
        $this->eventDispatcher->addListener(BlobAuthorizationEvent::class, static function (BlobAuthorizationEvent $event): void {
            $event->denyAccess();
        });
        $this->eventDispatcher->addListener(BlobAuthorizationEvent::class, static function (BlobAuthorizationEvent $event): void {
            $event->grantAccess();
        });
        $this->blobService->expects($this->never())->method('createSignedUrlForGivenQueryParameters');
        $this->expectException(HttpException::class);
        try {
            ($this->controller)($this->createRequest(['method' => 'DELETE', 'identifier' => '01981db0-8694-73ef-8e96-9d8c180ef6a7']));
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            throw $exception;
        } finally {
            $this->assertSame([], $this->handler->getRecords());
        }
    }

    /**
     * @param array<string, string> $queryParameters
     */
    private function createRequest(array $queryParameters): Request
    {
        $body = [];
        if (array_key_exists('metadata', $queryParameters)) {
            $body['metadata'] = $queryParameters['metadata'];
            unset($queryParameters['metadata']);
        }

        return Request::create('/cabinet/blob-urls?'.http_build_query($queryParameters), 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/ld+json'], $body === [] ? '' : json_encode($body, JSON_THROW_ON_ERROR));
    }
}
