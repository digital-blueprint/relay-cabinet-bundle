<?php

declare(strict_types=1);

namespace Dbp\Relay\CabinetBundle\DependencyInjection;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
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
        ->addRole(self::ROLE_USER, 'false', 'Returns true if the user is allowed to use the cabinet API.')
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
                ->arrayNode('typesense')
                    ->children()
                        ->scalarNode('api_url')
                            ->info('URL for the Typesense server')
                            ->isRequired()
                        ->end()
                        ->scalarNode('api_key')
                            ->info('API key for the Typesense server')
                            ->isRequired()
                        ->end()
                        ->integerNode('search_partitions')
                            ->info('Number of partitions the query is split into')
                            ->defaultValue(1)
                        ->end()
                        ->booleanNode('search_partitions_split_collection')
                            ->info('Whether the collection should be split for partitioning (requires a full sync on partition changes)')
                            ->defaultValue(false)
                        ->end()
                        ->integerNode('search_cache_ttl')
                            ->info('Number of seconds to cache search results at most')
                            ->defaultValue(3600)
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->append($this->getAuthNode())
            ->end();

        $treeBuilder->getRootNode()->append(BlobApi::getConfigNodeDefinition());

        return $treeBuilder;
    }
}
