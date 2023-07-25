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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA;
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\scripts\tools;

use Exception;
use oat\generis\model\DependencyInjection\ServiceOptions;
use oat\generis\model\DependencyInjection\ServiceOptionsInterface;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchClientFactory;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfig;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;

/**
 * Usage:
 *
 * php index.php 'oat\taoAdvancedSearch\scripts\tools\IndexMigration' -i tests -q '{"properties": {}}'
 */
class IndexMigration extends ScriptAction
{
    protected function provideUsage(): array
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Type this option to see the parameters.'
        ];
    }

    protected function provideOptions(): array
    {
        return [
            'index' => [
                'prefix' => 'i',
                'flag' => false,
                'description' => 'The index to apply the change',
                'required' => true
            ],
            'query' => [
                'prefix' => 'q',
                'flag' => false,
                'description' => 'The JSON query content to apply the change on the index',
                'required' => true
            ],
        ];
    }

    protected function provideDescription()
    {
        return 'Update indices on Elastic Search';
    }

    protected function run()
    {
        try {
            /** @var ServiceOptionsInterface $serviceOptions */
            $serviceOptions = $this->getServiceManager()->get(ServiceOptions::SERVICE_ID);

            $indexPrefixer = new IndexPrefixer(new ElasticSearchConfig($serviceOptions));
            $client = (new ElasticSearchClientFactory(new ElasticSearchConfig($serviceOptions)))->create();

            $index = $indexPrefixer->prefix($this->getOption('index'));
            $body = json_decode($this->getOption('query'), true);

            $client->indices()->putMapping(
                [
                    'index' => $index,
                    'body' => $body,
                ]
            );

            return Report::createSuccess(sprintf('Index "%s" map updated with "%s"', $index, json_encode($body)));
        } catch (Exception $exception) {
            return Report::createError(sprintf('Failed updating index mapping: %s', $exception->getMessage()));
        }
    }
}
