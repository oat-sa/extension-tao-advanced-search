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
 * Foundation, Inc., 31 Milk St # 960789 Boston, MA 02196 USA
 *
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Comment;

use Elastic\Elasticsearch\Client;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use RuntimeException;
use Throwable;

/**
 * Creates / ensures the shared resource-comments Elasticsearch index.
 */
class ItemCommentIndexManager
{
    private Client $client;
    private IndexPrefixer $indexPrefixer;
    private ?string $verifiedIndexName = null;

    public function __construct(Client $client, IndexPrefixer $indexPrefixer)
    {
        $this->client = $client;
        $this->indexPrefixer = $indexPrefixer;
    }

    public function ensureIndexExists(): string
    {
        if ($this->verifiedIndexName !== null) {
            return $this->verifiedIndexName;
        }

        $indexName = $this->getIndexName();

        try {
            $exists = $this->client->indices()->exists(['index' => $indexName])->asBool();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Unable to check resource-comments index existence: %s', $exception->getMessage()),
                0,
                $exception
            );
        }

        if (!$exists) {
            $definition = $this->getIndexDefinition();
            $definition['index'] = $indexName;

            try {
                $this->client->indices()->create($definition);
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    sprintf('Unable to create resource-comments index "%s": %s', $indexName, $exception->getMessage()),
                    0,
                    $exception
                );
            }
        }

        $this->assertIndexIsUsable($indexName);

        $this->verifiedIndexName = $indexName;

        return $indexName;
    }

    public function getIndexName(): string
    {
        return $this->indexPrefixer->prefix(ElasticsearchItemCommentAdapter::INDEX_NAME);
    }

    /**
     * @return array{index: string, body: array}
     */
    public function getIndexDefinition(): array
    {
        $file = dirname(__DIR__, 2) . '/config/resource-comments.conf.php';
        if (!is_readable($file)) {
            throw new RuntimeException(sprintf('Resource comments index config not readable: %s', $file));
        }

        /** @var array{index: string, body: array} $definition */
        $definition = require $file;

        return $definition;
    }

    private function assertIndexIsUsable(string $indexName): void
    {
        try {
            $health = $this->client->cluster()->health([
                'index' => $indexName,
                'level' => 'indices',
                'timeout' => '5s',
            ])->asArray();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf(
                    'Unable to verify resource-comments index "%s" health: %s',
                    $indexName,
                    $exception->getMessage()
                ),
                0,
                $exception
            );
        }

        $status = strtolower((string) ($health['status'] ?? ''));
        if ($status === 'red') {
            throw new RuntimeException(
                sprintf(
                    'Resource-comments index "%s" is red and cannot accept reads/writes. '
                    . 'Check Elasticsearch disk watermarks / shard allocation '
                    . '(cluster may be above cluster.routing.allocation.disk.watermark.high).',
                    $indexName
                )
            );
        }
    }
}
