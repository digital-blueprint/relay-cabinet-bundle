<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;

#[ApiResource(
    shortName: 'cabinet',
    types: ['https://schema.org/DigitalDocument'],
    operations: [
        new Post(
            uriTemplate: '/blob-urls',
            controller: BlobSignatureController::class,
            openapi: new Operation(
                tags: ['Cabinet'],
                summary: 'Returns a signed blob url which can be used to do operations on blob',
                parameters: [
                    new Parameter(
                        name: 'method',
                        in: 'query',
                        description: 'Blob method which will be used',
                        required: true,
                        schema: ['type' => 'string'],
                        example: 'GET'
                    ),
                    new Parameter(
                        name: 'type',
                        in: 'query',
                        description: 'Blob type which will be stored',
                        required: false,
                        schema: ['type' => 'string'],
                        example: ''
                    ),
                    new Parameter(
                        name: 'prefix',
                        in: 'query',
                        description: 'Prefix to be stored for the blob resource',
                        required: false,
                        schema: ['type' => 'string'],
                        example: ''
                    ),
                    new Parameter(
                        name: 'identifier',
                        in: 'query',
                        description: 'Identifier of the blob resource',
                        required: false,
                        schema: ['type' => 'string'],
                        example: ''
                    ),
                    new Parameter(
                        name: 'includeData',
                        in: 'query',
                        description: '1 if GET request should return base64 encoded filedata, not given otherwise',
                        required: false,
                        schema: ['type' => 'string'],
                        example: ''
                    ),
                    new Parameter(
                        name: 'deleteIn',
                        in: 'query',
                        description: 'Iso8601 duration in which the file should be deleted',
                        required: false,
                        schema: ['type' => 'string'],
                        example: 'P1D'
                    ),
                ]
            ),
            normalizationContext: [
                'groups' => ['Cabinet:output'],
                'jsonld_embed_context' => true,
            ],
            read: false,
            name: 'signature'
        ),
    ],
    routePrefix: '/cabinet',
    normalizationContext: [
        'groups' => ['Cabinet:output'],
        'jsonld_embed_context' => true,
    ]
)]
class Dummy
{
}
