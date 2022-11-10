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

namespace oat\taoAdvancedSearch\model\Resource\ServiceProvider;

use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use oat\oatbox\log\LoggerService;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilder;
use oat\tao\model\search\SearchProxy;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoAdvancedSearch\model\Resource\Factory\IndexDocumentBuilderFactory;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexationProcessor;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexer;
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

        $services->set(IndexDocumentBuilder::class, IndexDocumentBuilder::class)
            ->factory(
                [
                    IndexDocumentBuilderFactory::class,
                    'getIndexDocumentBuilder'
                ])
            ->private();

        $services->set(ResourceIndexationProcessor::class, ResourceIndexationProcessor::class)
            ->args(
                 [
                    service(LoggerService::SERVICE_ID),
                    service(IndexDocumentBuilder::class),
                    service(SearchProxy::SERVICE_ID)
                 ]
            )->public();
    }
}
