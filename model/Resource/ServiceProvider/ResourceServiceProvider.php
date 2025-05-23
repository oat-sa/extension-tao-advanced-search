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
 * Copyright (c) 2022-2025 (original work) Open Assessment Technologies SA;
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Resource\ServiceProvider;

use oat\generis\model\data\Ontology;
use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\featureFlag\FeatureFlagChecker;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoAdvancedSearch\model\Resource\Service\ItemClassRelationService;
use oat\taoAdvancedSearch\model\Resource\Service\ItemRelationsService;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexer;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @codeCoverageIgnore
 */
class ResourceServiceProvider implements ContainerServiceProviderInterface
{
    public function __invoke(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();

        $services->set(ResourceIndexer::class, ResourceIndexer::class)
            ->args(
                [
                    service(QueueDispatcherInterface::SERVICE_ID),
                ]
            )->public();

        $services->set(ItemRelationsService::class)
            ->args([
                service(ElasticSearch::class),
                service(AdvancedSearchChecker::class),
                service(FeatureFlagChecker::class)
            ])
            ->public();

        $services->set(ItemClassRelationService::class)
            ->args([
                service(ElasticSearch::class),
                service(AdvancedSearchChecker::class),
                service(Ontology::SERVICE_ID),
                service(FeatureFlagChecker::class)
            ])
            ->public();
    }
}
