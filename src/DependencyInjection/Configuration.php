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
    public const ROLE_GROUP_READER_METADATA = 'ROLE_GROUP_READER_METADATA';
    public const ROLE_GROUP_READER_CONTENT = 'ROLE_GROUP_READER_CONTENT';
    public const ROLE_GROUP_WRITER = 'ROLE_GROUP_WRITER';
    public const ROLE_GROUP_WRITER_READ_ADDRESS = 'ROLE_GROUP_WRITER_READ_ADDRESS';
    public const ROLE_USER = 'ROLE_USER';

    /** Attributes */
    public const ATTRIBUTE_GROUPS = 'GROUPS';

    /** Config nodes */
    public const GROUP_NODE = 'group';
    public const GROUP_DATA_ADDRESS_ATTRIBUTES_NODE = 'address_attributes';
    public const GROUP_DATA_IRI_TEMPLATE = 'iri_template';
    public const GROUP_STREET_ATTRIBUTE = 'street';
    public const GROUP_LOCALITY_ATTRIBUTE = 'locality';
    public const GROUP_POSTAL_CODE_ATTRIBUTE = 'postal_code';
    public const GROUP_COUNTRY_ATTRIBUTE = 'country';

    private function getGroupNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(self::GROUP_NODE);

        $node = $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode(self::GROUP_DATA_IRI_TEMPLATE)
                    ->defaultValue('/base/organizations/%s')
                ->end()
                ->arrayNode(self::GROUP_DATA_ADDRESS_ATTRIBUTES_NODE)
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode(self::GROUP_STREET_ATTRIBUTE)
                            ->defaultValue('streetAddress')
                        ->end()
                        ->scalarNode(self::GROUP_LOCALITY_ATTRIBUTE)
                            ->defaultValue('addressLocality')
                        ->end()
                        ->scalarNode(self::GROUP_POSTAL_CODE_ATTRIBUTE)
                            ->defaultValue('postalCode')
                        ->end()
                        ->scalarNode(self::GROUP_COUNTRY_ATTRIBUTE)
                            ->defaultValue('addressCountry')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    private function getAuthNode(): NodeDefinition
    {
        return AuthorizationConfigDefinition::create()
        ->addPolicy(self::ROLE_USER, 'false', 'Returns true if the user is allowed to use the cabinet API.')
        ->addPolicy(self::ROLE_GROUP_READER_METADATA, 'false', 'Returns true if the user has read access for the given group, limited to metadata.')
        ->addPolicy(self::ROLE_GROUP_READER_CONTENT, 'false', 'Returns true if the user has read access for the given group, including delivery content. Implies the metadata reader role.')
        ->addPolicy(self::ROLE_GROUP_WRITER, 'false', 'Returns true if the user has write access for the given group. Implies all reader roles.')
        ->addPolicy(self::ROLE_GROUP_WRITER_READ_ADDRESS, 'false', 'Returns true if the user has write access for the given group and can read recipient addresses. Implies all reader/writer roles.')
        ->addAttribute(self::ATTRIBUTE_GROUPS, '[]', 'Returns an array of available group IDs.')
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
            ->append($this->getGroupNode())
            ->append($this->getAuthNode())
            ->end();

        return $treeBuilder;
    }
}
