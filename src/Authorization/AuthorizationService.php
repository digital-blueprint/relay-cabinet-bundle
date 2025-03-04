<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Authorization;

use Dbp\Relay\CabinetBundle\DependencyInjection\Configuration;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;

class AuthorizationService extends AbstractAuthorizationService
{
    /**
     * Check if the user can access the application at all.
     */
    public function checkCanUse(): void
    {
        $this->denyAccessUnlessIsGrantedRole(Configuration::ROLE_USER);
    }

    /**
     * Returns if the user can use the application at all.
     */
    public function getCanUse(): bool
    {
        return $this->isGrantedRole(Configuration::ROLE_USER);
    }

    public function validateConfiguration()
    {
        $this->getCanUse();
    }
}
