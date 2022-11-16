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

use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use oat\oatbox\log\LoggerService;
use oat\taoAdvancedSearch\model\Index\Listener\AgnosticEventListener;
use oat\taoAdvancedSearch\model\Index\Listener\HandlerMap;
use oat\taoAdvancedSearch\model\Metadata\Specification\PropertyAllowedSpecification;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexationProcessor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @codeCoverageIgnore
 */
class IndexServiceProvider implements ContainerServiceProviderInterface
{
    public function __invoke(ContainerConfigurator $configurator): void
    {
        // $this->setParameters($configurator);

        $services = $configurator->services();

        $services->set(HandlerMap::class, HandlerMap::class)
            ->args(
                [
                    // Mappings: Event => [Handlers]
                    [

                    ]
                ]
            )->private();

        $services->set(AgnosticEventListener::class, AgnosticEventListener::class)
            ->args(
                [
                    service(HandlerMap::class),
                    service(ResourceIndexationProcessor::class),
                    service(LoggerService::SERVICE_ID),
                ]
            )->public();
    }
}
