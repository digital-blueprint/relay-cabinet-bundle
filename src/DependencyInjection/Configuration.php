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
                ->arrayNode('blob')
                    ->children()
                        ->scalarNode('api_url')
                            ->info('URL for blob storage API')
                            ->isRequired()
                        ->end()
                        ->scalarNode('bucket_id')
                            ->info('Bucket id for blob storage')
                            ->isRequired()
                        ->end()
                        ->scalarNode('bucket_key')
                            ->info('Secret key for blob storage')
                            ->isRequired()
                        ->end()
                        ->booleanNode('use_api')
                            ->info('If the HTTP API should be used for communicating with blob')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('api_url_internal')
                            ->info('URL for blob storage API when connecting internally (defaults to url)')
                        ->end()
                        ->scalarNode('idp_url')
                            ->info('IDP URL for authenticating with blob')
                        ->end()
                        ->scalarNode('idp_client_id')
                            ->info('Client ID for authenticating with blob')
                        ->end()
                        ->scalarNode('idp_client_secret')
                            ->info('Client secret for authenticating with blob')
                        ->end()
                    ->end()
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
                    ->end()
                ->end()
            ->end()
            ->append($this->getAuthNode())
            ->end();

        return $treeBuilder;
    }
}
