<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\TypesenseSync;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;

#[ApiResource(
    shortName: 'CabinetSyncPersonAction',
    operations: [
        new Post(
            uriTemplate: '/sync-person-actions',
            controller: SyncController::class,
            openapi: new Operation(
                tags: ['Cabinet'],
                summary: 'Trigger a sync of one person',
                parameters: [
                    new Parameter(
                        name: 'person_id',
                        in: 'query',
                        description: 'Sync the person data for the provided person ID',
                        required: false,
                        schema: ['type' => 'string']
                    ),
                    new Parameter(
                        name: 'documentId',
                        in: 'query',
                        description: 'Sync the person data for the provided document ID',
                        required: false,
                        schema: ['type' => 'string']
                    ),
                ]
            ),
            normalizationContext: [
                'groups' => ['Cabinet:output'],
                'jsonld_embed_context' => false,
            ],
            read: false
        ),
    ],
    routePrefix: '/cabinet',
    normalizationContext: [
        'groups' => ['Cabinet:output'],
        'jsonld_embed_context' => false,
    ]
)]
class SyncPersonAction
{
}
