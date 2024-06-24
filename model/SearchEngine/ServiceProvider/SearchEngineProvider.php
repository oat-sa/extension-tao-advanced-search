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

use Elastic\Elasticsearch\Client;
use oat\generis\model\data\permission\PermissionInterface;
use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use oat\generis\model\DependencyInjection\ServiceOptions;
use oat\oatbox\log\LoggerService;
use oat\oatbox\session\SessionService;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchClientFactory;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfig;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchIndexer;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\QueryBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Normalizer\SearchResultNormalizer;
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
            ->args(
                [
                    service(ElasticSearchConfig::class),
                ]
            )
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

        $services->set(ElasticSearchConfig::class, ElasticSearchConfig::class)
            ->args(
                [
                    service(ServiceOptions::SERVICE_ID),
                ]
            )->public();

        $services->set(ElasticSearchClientFactory::class, ElasticSearchClientFactory::class)
            ->args(
                [
                    service(ElasticSearchConfig::class),
                ]
            )
            ->public();

        $services->set(Client::class, Client::class)
            ->factory([service(ElasticSearchClientFactory::class), 'create'])
            ->public();

        $services->set(ElasticSearch::class, ElasticSearch::class)
            ->args(
                [
                    service(Client::class),
                    service(QueryBuilder::class),
                    service(ElasticSearchIndexer::class),
                    service(IndexPrefixer::class),
                    service(LoggerService::SERVICE_ID),
                    service(SearchResultNormalizer::class),
                ]
            )->public();

        $services->set(ElasticSearchIndexer::class, ElasticSearchIndexer::class)
            ->args(
                [
                    service(Client::class),
                    service(LoggerService::SERVICE_ID),
                    service(IndexPrefixer::class),
                ]
            )->public();

        $services->set(SearchResultNormalizer::class, SearchResultNormalizer::class)
            ->public();
    }
}
