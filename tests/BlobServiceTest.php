<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Tests;

use Dbp\Relay\CabinetBundle\Blob\BlobService;
use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BlobServiceTest extends TestCase
{
    private BlobService $service;

    protected function setUp(): void
    {
        $this->service = new BlobService(new ConfigurationService(), $this->createMock(ContainerInterface::class));
        $this->service->setConfig(['blob_library' => [
            'bucket_identifier' => 'cabinet',
            'use_http_mode' => true,
            'http_mode' => [
                'bucket_key' => 'test-signing-secret-at-least-32-bytes',
                'blob_base_url' => 'https://blob.invalid',
                'oidc_enabled' => false,
            ],
        ]]);
    }

    #[TestWith(['POST', null, 'metadata'])]
    #[TestWith(['POST', '0', 'metadata'])]
    #[TestWith(['POST', '1', 'metadata,file,fileName'])]
    #[TestWith(['PATCH', null, 'metadata'])]
    #[TestWith(['PATCH', '0', 'metadata'])]
    #[TestWith(['PATCH', '1', 'metadata,file,fileName'])]
    public function testSignsDerivedAllowedFields(string $method, ?string $updateFile, string $allowedFields): void
    {
        $parameters = $this->getWriteParameters($method);
        if ($updateFile !== null) {
            $parameters['updateFile'] = $updateFile;
        }

        $url = $this->service->createSignedUrlForGivenQueryParameters($parameters);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame($allowedFields, $query['allowedFields']);
        $this->assertArrayNotHasKey('updateFile', $query);
        $this->assertArrayNotHasKey('metadata', $query);
        $this->assertSame(hash('sha256', $parameters['metadata']), $query['metadataHash']);
    }

    public function testReadUrlHasNoBodyAllowlist(): void
    {
        $url = $this->service->createSignedUrlForGivenQueryParameters(['method' => 'GET', 'identifier' => 'file-id']);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertArrayNotHasKey('allowedFields', $query);
        $this->assertArrayNotHasKey('updateFile', $query);
    }

    #[TestWith(['updateFile', 'true'])]
    #[TestWith(['updateFile', ['1']])]
    #[TestWith(['allowedFields', 'metadata,file,fileName'])]
    public function testRejectsInvalidOrClientControlledPermissions(string $parameter, mixed $value): void
    {
        $parameters = $this->getWriteParameters('PATCH');
        $parameters[$parameter] = $value;

        $this->expectException(ApiError::class);
        $this->service->createSignedUrlForGivenQueryParameters($parameters);
    }

    public function testRejectsFileUpdateFlagForReadRequests(): void
    {
        $this->expectException(ApiError::class);
        $this->service->validateSignedUrlQueryParameters([
            'method' => 'GET', 'identifier' => 'file-id', 'updateFile' => '1',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getWriteParameters(string $method): array
    {
        return $method === 'POST'
            ? ['method' => 'POST', 'prefix' => 'documents', 'type' => 'document', 'metadata' => '{"owner":"jane"}']
            : ['method' => 'PATCH', 'identifier' => 'file-id', 'metadata' => '{"owner":"jane"}'];
    }
}
