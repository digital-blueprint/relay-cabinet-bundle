<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\DependencyInjection;

use Dbp\Relay\CabinetBundle\Authorization\AuthorizationService;
use Dbp\Relay\CabinetBundle\Blob\TypesenseTask;
use Dbp\Relay\CabinetBundle\Service\ConfigurationService;
use Dbp\Relay\CoreBundle\Extension\ExtensionTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayCabinetExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    public function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $pathsToHide = [
            '/cabinet/typesense',
        ];

        foreach ($pathsToHide as $path) {
            $this->addPathToHide($container, $path);
        }

        $this->addRouteResource($container, __DIR__.'/../Resources/config/routes.yaml', 'yaml');

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $definition = $container->getDefinition('Dbp\Relay\CabinetBundle\Service\CabinetService');
        $definition->addMethodCall('setConfig', [$mergedConfig]);

        $definition = $container->getDefinition(AuthorizationService::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);

        $definition = $container->getDefinition(ConfigurationService::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach (['doctrine', 'doctrine_migrations'] as $extKey) {
            if (!$container->hasExtension($extKey)) {
                throw new \Exception("'".$this->getAlias()."' requires the '$extKey' bundle to be loaded!");
            }
        }

        $container->prependExtensionConfig('doctrine', [
            'dbal' => [
                'connections' => [
                    'dbp_relay_cabinet_bundle' => [
                        'url' => $config['database_url'] ?? '',
                    ],
                ],
            ],
            'orm' => [
                'entity_managers' => [
                    'dbp_relay_cabinet_bundle' => [
                        'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                        'connection' => 'dbp_relay_cabinet_bundle',
                        'mappings' => [
                            'dbp_relay_cabinet' => [
                                'type' => 'attribute',
                                'dir' => __DIR__.'/../Entity',
                                'prefix' => 'Dbp\Relay\CabinetBundle\Entity',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->registerEntityManager($container, 'dbp_relay_cabinet_bundle');

        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Dbp\Relay\CabinetBundle\Migrations' => __DIR__.'/../Migrations',
            ],
        ]);

        $this->addQueueMessageClass($container, TypesenseTask::class);
    }
}
