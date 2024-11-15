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
 * Copyright (c) 2021-2022 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Metadata\ServiceProvider;

use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use oat\generis\persistence\PersistenceServiceProvider;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\featureFlag\FeatureFlagChecker;
use oat\tao\model\featureFlag\Service\FeatureBasedPropertiesService;
use oat\tao\model\Lists\Business\Service\ClassMetadataSearcherProxy;
use oat\tao\model\search\Service\DefaultSearchSettingsService;
use oat\taoAdvancedSearch\model\Metadata\Service\AdvancedSearchSettingsService;
use oat\taoAdvancedSearch\model\Metadata\Service\ListSavedEventListener;
use oat\taoAdvancedSearch\model\Metadata\Specification\PropertyAllowedSpecification;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @codeCoverageIgnore
 */
class MetadataServiceProvider implements ContainerServiceProviderInterface
{
    public function __invoke(ContainerConfigurator $configurator): void
    {
        $this->setParameters($configurator);

        $services = $configurator->services();

        $services->set(PropertyAllowedSpecification::class, PropertyAllowedSpecification::class)
            ->args(
                [
                    param(PropertyAllowedSpecification::CONFIG_BLACK_LIST)
                ]
            )->public();

        $services->set(AdvancedSearchSettingsService::class, AdvancedSearchSettingsService::class)
            ->args(
                [
                    service(ClassMetadataSearcherProxy::SERVICE_ID),
                    service(DefaultSearchSettingsService::class),
                    service(AdvancedSearchChecker::class),
                    service(FeatureFlagChecker::class),
                    service(FeatureBasedPropertiesService::class),
                ]
            )->public();

        $services->set(ListSavedEventListener::class, ListSavedEventListener::class)
            ->args(
                [
                    service(ResourceIndexer::class),
                    service(PersistenceServiceProvider::DEFAULT_QUERY_BUILDER)
                ]
            )->public();
    }

    private function setParameters(ContainerConfigurator $configurator): void
    {
        $parameters = $configurator->parameters();
        $parameters->set(
            PropertyAllowedSpecification::CONFIG_BLACK_LIST,
            array_filter(
                explode(
                    ',',
                    (string)getenv(PropertyAllowedSpecification::CONFIG_BLACK_LIST)
                )
            )
        );
    }
}
