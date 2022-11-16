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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Index\ServiceProvider;

use oat\generis\model\data\event\ResourceDeleted;
use oat\generis\model\data\event\ResourceUpdated;
use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use oat\oatbox\log\LoggerService;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilder;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\Index\Handler\ResourceDeletedHandler;
use oat\taoAdvancedSearch\model\Index\Handler\ResourceUpdatedHandler;
use oat\taoAdvancedSearch\model\Index\Handler\TestUpdatedHandler;
use oat\taoAdvancedSearch\model\Index\Listener\AgnosticEventListener;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexationProcessor;
use oat\taoTests\models\event\TestUpdatedEvent;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @codeCoverageIgnore
 */
class IndexServiceProvider implements ContainerServiceProviderInterface
{
    public function __invoke(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();

        $services->set(ResourceUpdatedHandler::class, ResourceUpdatedHandler::class)
            ->args([
                service(LoggerService::SERVICE_ID),
                service(IndexDocumentBuilder::class),
                service(SearchProxy::SERVICE_ID)
            ]);

        $services->set(ResourceDeletedHandler::class, ResourceDeletedHandler::class)
            ->args([
                service(LoggerService::SERVICE_ID),
                service(ResourceIndexationProcessor::class),
            ]);

        $services->set(TestUpdatedHandler::class, TestUpdatedHandler::class)
            ->args([
                service(LoggerService::SERVICE_ID),
                service(IndexDocumentBuilder::class),
                service(SearchProxy::SERVICE_ID)
            ]);

        $services->set(AgnosticEventListener::class, AgnosticEventListener::class)
            ->args(
                [
                    service(LoggerService::SERVICE_ID),
                    [
                        ResourceUpdated::class => [
                            service(ResourceUpdatedHandler::class)
                        ],
                        ResourceDeleted::class => [
                            service(ResourceDeletedHandler::class)
                        ],
                        TestUpdatedEvent::class => [
                            service(TestUpdatedHandler::class)
                        ]
                    ]
                ]
            )->public();
    }
}
