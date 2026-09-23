<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\Tests;

use Dbp\Relay\BasePersonBundle\DbpRelayBasePersonBundle;
use Dbp\Relay\BlobBundle\DbpRelayBlobBundle;
use Dbp\Relay\BlobBundle\TestUtils\BlobTestUtils;
use Dbp\Relay\CabinetBundle\DbpRelayCabinetBundle;
use Dbp\Relay\CoreBundle\TestUtils\CoreTestKernelTrait;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use CoreTestKernelTrait;

    protected function registerAdditionalBundles(): iterable
    {
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new DbpRelayBasePersonBundle();
        yield new DbpRelayCabinetBundle();
        yield new DbpRelayBlobBundle();
    }

    protected function configureAdditionalContainer(ContainerConfigurator $container): void
    {
        $container->extension('dbp_relay_cabinet', [
            'database_url' => 'mysql://dummy:dummy@dummy',
        ]);

        $container->extension('dbp_relay_blob', BlobTestUtils::getTestConfig());
    }
}
