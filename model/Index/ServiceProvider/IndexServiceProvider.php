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
 * Copyright (c) 2022-2023 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Index\ServiceProvider;

use oat\generis\model\DependencyInjection\ContainerServiceProviderInterface;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\taoAdvancedSearch\model\Index\Service\AdvancedSearchIndexDocumentBuilder;
use oat\taoAdvancedSearch\model\Index\Service\RecreatingIndexService;
use oat\taoAdvancedSearch\model\Resource\Service\IndiciesConfigurationService;
use oat\taoAdvancedSearch\model\Test\Normalizer\TestNormalizer;
use oat\taoMediaManager\model\relation\service\IdDiscoverService;
use oat\taoQtiItem\model\qti\parser\ElementReferencesExtractor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Elastic\Elasticsearch\Client;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @codeCoverageIgnore
 */
class IndexServiceProvider implements ContainerServiceProviderInterface
{
    public function __invoke(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();

        $services->set(AdvancedSearchIndexDocumentBuilder::class, AdvancedSearchIndexDocumentBuilder::class)
            ->args([
                service(ElementReferencesExtractor::class),
                service(IndexDocumentBuilderInterface::class),
                service(IdDiscoverService::class),
                service(TestNormalizer::class),
            ])->public();
    }
}
