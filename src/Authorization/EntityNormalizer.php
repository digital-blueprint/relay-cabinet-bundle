<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Authorization;

use Dbp\Relay\CoreBundle\Authorization\AuthorizationConfigDefinition;
use Dbp\Relay\CoreBundle\Authorization\Serializer\AbstractEntityDeNormalizer;
use Dbp\Relay\CoreBundle\Helpers\Tools;

class EntityNormalizer extends AbstractEntityDeNormalizer
{
    /** @var AuthorizationService */
    private $authoriziationService;

    public function __construct(AuthorizationService $authorizationService)
    {
        $this->authoriziationService = $authorizationService;

        //        $this->configureEntities([
        //            'CabinetRequest' => [
        //                AuthorizationConfigDefinition::ENTITY_CLASS_NAME_CONFIG_NODE => Request::class,
        //            ],
        //            'CabinetRequestRecipient' => [
        //                AuthorizationConfigDefinition::ENTITY_CLASS_NAME_CONFIG_NODE => RequestRecipient::class,
        //            ],
        //        ]);
    }

    protected function onNormalize(object $entity, string $entityShortName, array &$context)
    {
        $attributesToShow = [];

        //        if ($entity instanceof Request) {
        //            if ($this->authoriziationService->getCanReadContent($entity->getGroupId())) {
        //                $attributesToShow = [
        //                    'files',
        //                    'name',
        //                    ];
        //            }
        //        } elseif ($entity instanceof RequestRecipient) {
        //            // personal address of recipients is returned if
        //            // - it was entered manually by a user (i.e. person identifier is not set) OR
        //            // - the current user has write and read personal address permissions for the group
        //            if (Tools::isNullOrEmpty($entity->getPersonIdentifier()) || $this->authoriziationService->canReadInternalAddresses($entity->getCabinetRequest()->getGroupId())) {
        //                $attributesToShow = [
        //                    'addressCountry',
        //                    'postalCode',
        //                    'addressLocality',
        //                    'streetAddress',
        //                    'buildingNumber',
        //                ];
        //            }
        //
        //            // birthdate of recipients is returned if
        //            // - it was entered manually by a user (i.e. person identifier is not set) AND the current user at least has content read permissions for the group
        //            if (Tools::isNullOrEmpty($entity->getPersonIdentifier()) && $this->authoriziationService->getCanReadContent($entity->getCabinetRequest()->getGroupId())) {
        //                $attributesToShow[] = 'birthDate';
        //            }
        //        }

        self::showAttributes($context, $entityShortName, $attributesToShow);
    }
}
