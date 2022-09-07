<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA;
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\SearchEngine\ServiceProvider;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use oat\generis\model\data\permission\PermissionInterface;
use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use oat\oatbox\log\LoggerService;
use oat\oatbox\session\SessionService;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchIndexer;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\QueryBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use oat\taoAdvancedSearch\model\SearchEngine\Specification\UseAclSpecification;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class SearchEngineProvider implements ContainerServiceProviderInterface
{
    public function __invoke(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();

        $services->set(IndexPrefixer::class, IndexPrefixer::class)
            ->public();

        $services->set(UseAclSpecification::class, UseAclSpecification::class)
            ->public();

        $services->set(QueryBuilder::class, QueryBuilder::class)
            ->args(
                [
                    service(LoggerService::SERVICE_ID),
                    service(PermissionInterface::SERVICE_ID),
                    service(SessionService::SERVICE_ID),
                    service(IndexPrefixer::class),
                    service(UseAclSpecification::class),
                ]
            )->public();

        $services->set(Client::class, Client::class)
            ->factory(ClientBuilder::class . '::fromConfig')
            ->args(
                [
                    [
                        //@TODO Get config from configuration
                        'hosts' => array(
                            array(
                                'scheme' => 'http',
                                'host' => 'advanced-search-tao-elasticsearch',
                                'port' => '9200'
                            )
                        ),
//                        'settings' => array(
//                            'analysis' => array(
//                                'filter' => array(
//                                    'autocomplete_filter' => array(
//                                        'type' => 'edge_ngram',
//                                        'min_gram' => 1,
//                                        'max_gram' => 100
//                                    )
//                                ),
//                                'analyzer' => array(
//                                    'autocomplete' => array(
//                                        'type' => 'custom',
//                                        'tokenizer' => 'standard',
//                                        'filter' => array(
//                                            'lowercase',
//                                            'autocomplete_filter'
//                                        )
//                                    )
//                                )
//                            )
//                        ),
                    ],
                ]
            )
            ->public();

        $services->set(ElasticSearch::class, ElasticSearch::class)
            ->args(
                [
                    service(Client::class),
                    service(QueryBuilder::class),
                    service(ElasticSearchIndexer::class),
                    service(IndexPrefixer::class),
                    service(LoggerService::SERVICE_ID),
                    [],
                ]
            )->public();

        $services->set(ElasticSearchIndexer::class, ElasticSearchIndexer::class)
            ->args(
                [
                    service(Client::class),
                    service(LoggerService::SERVICE_ID),
                    service(IndexPrefixer::class),
                    [],
                ]
            )->public();
    }
}
