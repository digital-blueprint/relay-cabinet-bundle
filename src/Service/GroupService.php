<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Service;

use ApiPlatform\Api\IriConverterInterface;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\LocalData\LocalData;
use Dbp\Relay\CoreBundle\LocalData\LocalDataAwareInterface;
use Dbp\Relay\CoreBundle\Rest\Entity\NamedEntityInterface;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\CoreBundle\Rest\Query\Parameters;
use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CabinetBundle\DependencyInjection\Configuration;
use Dbp\Relay\CabinetBundle\Entity\Group;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;

class GroupService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var AuthorizationService */
    private $auth;

    /** @var IriConverterInterface */
    private $iriConverter;

    /** @var array */
    private $config;

    public function __construct(AuthorizationService $auth, IriConverterInterface $iriConverter)
    {
        $this->config = [];
        $this->auth = $auth;
        $this->iriConverter = $iriConverter;
    }

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    public function getGroupById(string $identifier, array $options = []): Group
    {
        return $this->createGroup($identifier);
    }

    public function getGroups(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array
    {
        $groups = $this->auth->getGroupIdsForCurrentUser();
        $groupsMyAccess = [];
        foreach (array_slice($groups, Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage), $maxNumItemsPerPage) as $groupId) {
            $groupsMyAccess[] = $this->createGroup($groupId);
        }

        return $groupsMyAccess;
    }

    private function createGroup(string $groupId): Group
    {
        $filters = [];
        Parameters::setIncludeLocal($filters, LocalData::getQueryParameterFromLocalDataAttributes($this->getAddressAttributes()));

        $entity = $this->iriConverter->getResourceFromIri(sprintf($this->config[Configuration::GROUP_DATA_IRI_TEMPLATE], $groupId),
            ['filters' => $filters]);

        if ($entity instanceof NamedEntityInterface === false
            || $entity instanceof LocalDataAwareInterface === false) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'object not suitable to provide group name and address');
        }

        $group = new Group();
        $group->setIdentifier($entity->getIdentifier());
        $group->setName($entity->getName());
        $group->setStreet($entity->getLocalDataValue(
            $this->getAddressAttributes()[Configuration::GROUP_STREET_ATTRIBUTE]) ?? '');
        $group->setPostalCode($entity->getLocalDataValue(
            $this->getAddressAttributes()[Configuration::GROUP_POSTAL_CODE_ATTRIBUTE]) ?? '');
        $group->setLocality($entity->getLocalDataValue(
            $this->getAddressAttributes()[Configuration::GROUP_LOCALITY_ATTRIBUTE]) ?? '');
        $group->setCountry($entity->getLocalDataValue(
            $this->getAddressAttributes()[Configuration::GROUP_COUNTRY_ATTRIBUTE]) ?? '');

        foreach ($this->auth->getGroupRolesForCurrentUser($groupId) as $groupRole) {
            $group->addGroupRole($groupRole);
        }

        return $group;
    }

    private function getAddressAttributes(): array
    {
        return $this->config[Configuration::GROUP_DATA_ADDRESS_ATTRIBUTES_NODE];
    }
}
