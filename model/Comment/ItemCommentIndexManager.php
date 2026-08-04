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
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Comment;

use Elastic\Elasticsearch\Client;
use oat\generis\model\DependencyInjection\ServiceOptions;
use oat\oatbox\service\ConfigurableService;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchClientFactory;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfig;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use RuntimeException;
use Throwable;

/**
 * Creates / ensures the dedicated item-comments Elasticsearch index.
 */
class ItemCommentIndexManager extends ConfigurableService
{
    public const SERVICE_ID = 'taoAdvancedSearch/ItemCommentIndexManager';

    public function ensureIndexExists(): string
    {
        $indexName = $this->getIndexName();
        $client = $this->getClient();

        try {
            $exists = $client->indices()->exists(['index' => $indexName])->asBool();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Unable to check item-comments index existence: %s', $exception->getMessage()),
                0,
                $exception
            );
        }

        if ($exists) {
            return $indexName;
        }

        $definition = $this->getIndexDefinition();
        $definition['index'] = $indexName;

        try {
            $client->indices()->create($definition);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Unable to create item-comments index "%s": %s', $indexName, $exception->getMessage()),
                0,
                $exception
            );
        }

        return $indexName;
    }

    public function getIndexName(): string
    {
        return $this->getIndexPrefixer()->prefix(ElasticsearchItemCommentAdapter::INDEX_NAME);
    }

    /**
     * @return array{index: string, body: array}
     */
    public function getIndexDefinition(): array
    {
        $file = dirname(__DIR__, 2) . '/config/item-comments.conf.php';
        if (!is_readable($file)) {
            throw new RuntimeException(sprintf('Item comments index config not readable: %s', $file));
        }

        /** @var array{index: string, body: array} $definition */
        $definition = require $file;

        return $definition;
    }

    private function getClient(): Client
    {
        $locator = $this->getServiceLocator();
        if ($locator->has(Client::class)) {
            return $locator->get(Client::class);
        }

        if (method_exists($locator, 'getContainer')) {
            try {
                return $locator->getContainer()->get(Client::class);
            } catch (Throwable $exception) {
                // Fall through to ServiceOptions factory.
            }
        }

        $serviceOptions = $locator->get(ServiceOptions::SERVICE_ID);

        return (new ElasticSearchClientFactory(new ElasticSearchConfig($serviceOptions)))->create();
    }

    private function getIndexPrefixer(): IndexPrefixer
    {
        $locator = $this->getServiceLocator();
        if ($locator->has(IndexPrefixer::class)) {
            return $locator->get(IndexPrefixer::class);
        }

        if (method_exists($locator, 'getContainer')) {
            try {
                return $locator->getContainer()->get(IndexPrefixer::class);
            } catch (Throwable $exception) {
                // Fall through to ServiceOptions factory.
            }
        }

        $serviceOptions = $locator->get(ServiceOptions::SERVICE_ID);

        return new IndexPrefixer(new ElasticSearchConfig($serviceOptions));
    }
}
