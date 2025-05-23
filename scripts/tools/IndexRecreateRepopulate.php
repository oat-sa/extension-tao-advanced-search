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
 * Copyright (c) 2025 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\scripts\tools;

use Elastic\Elasticsearch\Client;
use oat\generis\model\DependencyInjection\ServiceOptions;
use oat\generis\model\DependencyInjection\ServiceOptionsInterface;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\scripts\tools\MigrationAction;
use oat\taoAdvancedSearch\model\Resource\Task\ResourceMigrationTask;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchClientFactory;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfig;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use RuntimeException;

/**
 * php index.php 'oat\taoAdvancedSearch\scripts\tools\IndexRecreateRepopulate' -i tests
 */
class IndexRecreateRepopulate extends ScriptAction
{
    private Client $client;
    private IndexPrefixer $prefixer;

    protected function provideUsage(): array
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Recreates and repopulates a particular index'
        ];
    }

    protected function provideOptions(): array
    {
        return [
            'index' => [
                'prefix' => 'i',
                'flag' => false,
                'description' => 'The index to recreate',
                'required' => true
            ],
            'limit' => [
                'prefix' => 'l',
                'longPrefix' => 'limit',
                'flag' => false,
                'description' => 'The limit of resources to be processed per index',
                'defaultValue' => 50
            ],
        ];
    }

    protected function provideDescription(): string
    {
        return 'Recreate and repopulate a particular index';
    }

    /**
     * @inheritDoc
     */
    protected function run(): Report
    {
        $report = Report::createInfo('Recreating and repopulating index...');

        try {
            /** @var ServiceOptionsInterface $serviceOptions */
            $serviceOptions = $this->getServiceManager()->get(ServiceOptions::SERVICE_ID);
            $this->prefixer = new IndexPrefixer(new ElasticSearchConfig($serviceOptions));
            $this->client = (new ElasticSearchClientFactory(new ElasticSearchConfig($serviceOptions)))->create();

            $index = $this->getOption('index');
            $limit = $this->getOption('limit');
            $classUri = $this->findClassUri($index);

            $this->deleteIndex($index);
            $this->createIndex($index);

            $migration = new MigrationAction();
            $this->propagate($migration);
            $report->add(
                $migration->__invoke(
                    [
                        '-c', $limit,
                        '-cp', 'start=0&classUri=' . $classUri,
                        '-t', ResourceMigrationTask::class,
                        '-rp'
                    ]
                )
            );

            return Report::createSuccess(sprintf('Index "%s" recreation and repopulation action is complete', $index));
        } catch (\Exception $exception) {
            return Report::createError(sprintf('Failed recreating index: %s', $exception->getMessage()));
        }
    }

    private function deleteIndex(string $index)
    {
        return $this->client->indices()->delete(
            [
                'index' => $this->prefixer->prefix($index),
                'client' => [
                    'ignore' => 404
                ]
            ]
        )->asArray();
    }

    private function createIndex(string $chosenIndex)
    {
        $indexFile = $this->getIndexFile();

        $indexes = [];

        if ($indexFile && is_readable($indexFile)) {
            $indexes = require $indexFile;
        }

        foreach ($indexes as $index) {
            if ($index['index'] == $chosenIndex) {
                $index['index'] = $this->prefixer->prefix($index['index']);
                unset($index['body']['aliases']);

                $this->client->indices()->create($index);
            }
        }
    }

    private function getIndexFile(): string
    {
        return __DIR__ .
            DIRECTORY_SEPARATOR .
            '..' .
            DIRECTORY_SEPARATOR .
            '..' .
            DIRECTORY_SEPARATOR .
            'config' .
            DIRECTORY_SEPARATOR .
            'index.conf.php';
    }

    private function findClassUri($index): string
    {
        $indexClasses = array_flip(IndexerInterface::AVAILABLE_INDEXES);
        if (!isset($indexClasses[$index])) {
            throw new RuntimeException(
                sprintf('Provided index name "%s" does not correspond to a known class', $index)
            );
        }
        return $indexClasses[$index];
    }
}
