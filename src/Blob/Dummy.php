<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Blob;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    shortName: 'cabinet',
    types: ['https://schema.org/DigitalDocument'],
    operations: [
        new Post(
            uriTemplate: '/blob-urls',
            controller: BlobSignatureController::class,
            openapiContext: [
                'tags' => ['Cabinet'],
                'summary' => 'Returns a signed blob url which can be used to do operations on blob',
                'parameters' => [
                    [
                        'name' => 'method',
                        'in' => 'query',
                        'description' => 'Blob method which will be used',
                        'type' => 'string',
                        'required' => true,
                        'example' => 'GET',
                    ],
                    [
                        'name' => 'type',
                        'in' => 'query',
                        'description' => 'Blob type which will be stored',
                        'type' => 'string',
                        'required' => false,
                        'example' => '',
                    ],
                    [
                        'name' => 'prefix',
                        'in' => 'query',
                        'description' => 'Prefix to be stored for the blob resource',
                        'type' => 'string',
                        'required' => false,
                        'example' => '',
                    ],
                    [
                        'name' => 'identifier',
                        'in' => 'query',
                        'description' => 'Identifier of the blob resource',
                        'type' => 'string',
                        'required' => false,
                        'example' => '',
                    ],
                    [
                        'name' => 'includeData',
                        'in' => 'query',
                        'description' => '1 if GET request should return base64 encoded filedata, not given otherwise',
                        'type' => 'string',
                        'required' => false,
                        'example' => '',
                    ],
                    [
                        'name' => 'deleteIn',
                        'in' => 'query',
                        'description' => 'Iso8601 duration in which the file should be deleted',
                        'type' => 'string',
                        'required' => false,
                        'example' => 'P1D',
                    ],
                ],
            ],
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
