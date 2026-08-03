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
use oat\oatbox\service\ConfigurableService;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use oat\taoItems\model\Comment\ItemComment;
use oat\taoItems\model\Comment\ItemCommentPersistenceInterface;
use RuntimeException;

class ElasticsearchItemCommentAdapter extends ConfigurableService implements ItemCommentPersistenceInterface
{
    public const SERVICE_ID = 'taoAdvancedSearch/ElasticsearchItemCommentAdapter';
    public const INDEX_NAME = 'item-comments';

    public function create(ItemComment $comment): ItemComment
    {
        $document = $comment->toArray();
        $params = [
            'index' => $this->getIndexName(),
            'id' => $comment->getId(),
            'body' => $document,
            'refresh' => true,
        ];

        $response = $this->getClient()->index($params)->asArray();
        if (($response['result'] ?? null) !== 'created' && ($response['result'] ?? null) !== 'updated') {
            throw new RuntimeException('Failed to index item comment in Elasticsearch');
        }

        return $comment;
    }

    public function findByItemUri(string $itemUri): array
    {
        $params = [
            'index' => $this->getIndexName(),
            'body' => [
                'size' => 1000,
                'sort' => [
                    ['createdAt' => ['order' => 'asc']],
                ],
                'query' => [
                    'term' => [
                        'itemUri.keyword' => $itemUri,
                    ],
                ],
            ],
        ];

        try {
            $response = $this->getClient()->search($params)->asArray();
        } catch (\Throwable $exception) {
            // Fallback for indices where itemUri is already keyword-mapped.
            $params['body']['query'] = [
                'term' => [
                    'itemUri' => $itemUri,
                ],
            ];
            $response = $this->getClient()->search($params)->asArray();
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
                (string) ($source['status'] ?? ItemComment::STATUS_ACTIVE)
            );
        }

        return $comments;
    }

    public function countByItemUri(string $itemUri): int
    {
        $params = [
            'index' => $this->getIndexName(),
            'body' => [
                'query' => [
                    'term' => [
                        'itemUri.keyword' => $itemUri,
                    ],
                ],
            ],
        ];

        try {
            $response = $this->getClient()->count($params)->asArray();
        } catch (\Throwable $exception) {
            $params['body']['query'] = [
                'term' => [
                    'itemUri' => $itemUri,
                ],
            ];
            $response = $this->getClient()->count($params)->asArray();
        }

        return (int) ($response['count'] ?? 0);
    }

    private function getIndexName(): string
    {
        return $this->getIndexPrefixer()->prefix(self::INDEX_NAME);
    }

    private function getClient(): Client
    {
        return $this->getServiceLocator()->get(Client::class);
    }

    private function getIndexPrefixer(): IndexPrefixer
    {
        return $this->getServiceLocator()->get(IndexPrefixer::class);
    }
}
