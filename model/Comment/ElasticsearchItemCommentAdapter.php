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
use oat\taoItems\model\Comment\ItemComment;
use oat\taoItems\model\Comment\ItemCommentPersistenceInterface;
use RuntimeException;
use Throwable;

class ElasticsearchItemCommentAdapter implements ItemCommentPersistenceInterface
{
    public const INDEX_NAME = 'item-comments';

    private Client $client;
    private IndexPrefixer $indexPrefixer;
    private ItemCommentIndexManager $indexManager;

    public function __construct(
        Client $client,
        IndexPrefixer $indexPrefixer,
        ItemCommentIndexManager $indexManager
    ) {
        $this->client = $client;
        $this->indexPrefixer = $indexPrefixer;
        $this->indexManager = $indexManager;
    }

    public function create(ItemComment $comment): ItemComment
    {
        $this->indexManager->ensureIndexExists();

        $params = [
            'index' => $this->getIndexName(),
            'id' => $comment->getId(),
            'body' => $comment->toArray(),
            'refresh' => true,
        ];

        $response = $this->client->index($params)->asArray();
        if (($response['result'] ?? null) !== 'created' && ($response['result'] ?? null) !== 'updated') {
            throw new RuntimeException('Failed to index item comment in Elasticsearch');
        }

        return $comment;
    }

    public function findByItemUri(string $itemUri): array
    {
        $this->indexManager->ensureIndexExists();

        $params = [
            'index' => $this->getIndexName(),
            'body' => [
                'size' => 1000,
                'sort' => [
                    ['createdAt' => ['order' => 'asc']],
                ],
                'query' => [
                    'term' => [
                        'itemUri' => $itemUri,
                    ],
                ],
            ],
        ];

        try {
            $response = $this->client->search($params)->asArray();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Failed to search item comments in Elasticsearch: %s', $exception->getMessage()),
                0,
                $exception
            );
        }

        $comments = [];
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $source = $hit['_source'] ?? [];
            $comments[] = new ItemComment(
                (string) ($source['id'] ?? $hit['_id'] ?? ''),
                (string) ($source['itemUri'] ?? ''),
                (string) ($source['authorId'] ?? ''),
                (string) ($source['authorLabel'] ?? ''),
                (string) ($source['body'] ?? ''),
                (string) ($source['createdAt'] ?? ''),
                $this->toBool($source['edited'] ?? false),
                $this->toBool($source['resolved'] ?? false)
            );
        }

        return $comments;
    }

    private function getIndexName(): string
    {
        return $this->indexPrefixer->prefix(self::INDEX_NAME);
    }

    /**
     * @param mixed $value
     */
    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }
}
