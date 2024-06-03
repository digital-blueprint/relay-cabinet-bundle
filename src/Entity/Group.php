<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Entity;

use Symfony\Component\Serializer\Annotation\Groups;

class Group
{
    public const ROLE_READ_METADATA = 'rm';
    public const ROLE_READ_CONTENT = 'rc';
    public const ROLE_WRITE = 'w';
    public const ROLE_WRITE_READ_ADDRESS = 'wra';

    /**
     * @var string
     */
    #[Groups(['DispatchGroup:output'])]
    private $identifier;

    /**
     * @var string
     */
    #[Groups(['DispatchGroup:output'])]
    private $name;

    /**
     * @var string
     */
    #[Groups(['DispatchGroup:output'])]
    private $street;

    /**
     * @var string
     */
    #[Groups(['DispatchGroup:output'])]
    private $locality;

    /**
     * @var string
     */
    #[Groups(['DispatchGroup:output'])]
    private $postalCode;

    /**
     * @var string
     */
    #[Groups(['DispatchGroup:output'])]
    private $country;

    /**
     * @var string[]
     */
    #[Groups(['DispatchGroup:output'])]
    private $accessRights = [];

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    public function getLocality(): string
    {
        return $this->locality;
    }

    public function setLocality(string $locality): void
    {
        $this->locality = $locality;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): void
    {
        $this->postalCode = $postalCode;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function addGroupRole(string $groupRole): void
    {
        if (!in_array($groupRole, $this->accessRights, true) ?? (
            $groupRole === self::ROLE_READ_CONTENT
            || $groupRole === self::ROLE_READ_METADATA
            || $groupRole === self::ROLE_WRITE
            || $groupRole === self::ROLE_WRITE_READ_ADDRESS)) {
            $this->accessRights[] = $groupRole;
        }
    }

    public function getAccessRights(): array
    {
        return $this->accessRights;
    }
}
