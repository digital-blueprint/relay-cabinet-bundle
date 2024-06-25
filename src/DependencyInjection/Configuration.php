<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\DependencyInjection;

use Dbp\Relay\CoreBundle\Authorization\AuthorizationConfigDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /** Roles */
    public const ROLE_USER = 'ROLE_USER';

    private function getAuthNode(): NodeDefinition
    {
        return AuthorizationConfigDefinition::create()
        ->addPolicy(self::ROLE_USER, 'false', 'Returns true if the user is allowed to use the cabinet API.')
        ->getNodeDefinition();
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_cabinet');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('database_url')
                    ->isRequired()
                    ->info('The database DSN')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('blob_base_url')
                    ->info('Base URL for blob storage API')
                ->end()
                ->scalarNode('blob_bucket_id')
                    ->info('Bucket id for blob storage')
                ->end()
                ->scalarNode('blob_key')
                    ->info('Secret key for blob storage')
                ->end()
                ->scalarNode('typesense_base_url')
                    ->info('Base URL for the Typesense server')
                ->end()
                ->scalarNode('typesense_api_key')
                    ->info('API key for the Typesense server')
                ->end()
            ->end()
            ->append($this->getAuthNode())
            ->end();

        return $treeBuilder;
    }
}
