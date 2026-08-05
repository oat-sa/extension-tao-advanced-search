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
use Elastic\Elasticsearch\Exception\ClientResponseException;
use InvalidArgumentException;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use oat\taoItems\model\Comment\ItemComment;
use oat\taoItems\model\Comment\ItemCommentPersistenceInterface;
use oat\taoItems\model\Comment\ResourceCommentType;
use RuntimeException;
use Throwable;

class ElasticsearchItemCommentAdapter implements ItemCommentPersistenceInterface
{
    public const INDEX_NAME = 'resource-comments';

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
            'body' => $this->toDocument($comment),
            'refresh' => true,
        ];

        try {
            $response = $this->client->index($params)->asArray();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf(
                    'Failed to index resource comment "%s" in Elasticsearch: %s',
                    $comment->getId(),
                    $exception->getMessage()
                ),
                0,
                $exception
            );
        }

        if (!in_array($response['result'] ?? null, ['created', 'updated'], true)) {
            throw new RuntimeException(
                sprintf('Failed to index resource comment "%s" in Elasticsearch', $comment->getId())
            );
        }

        return $comment;
    }

    public function update(ItemComment $comment): ItemComment
    {
        $this->indexManager->ensureIndexExists();

        $params = [
            'index' => $this->getIndexName(),
            'id' => $comment->getId(),
            'body' => $this->toDocument($comment),
            'refresh' => true,
        ];

        try {
            $response = $this->client->index($params)->asArray();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Failed to update resource comment in Elasticsearch: %s', $exception->getMessage()),
                0,
                $exception
            );
        }

        if (!in_array($response['result'] ?? null, ['created', 'updated'], true)) {
            throw new RuntimeException('Failed to update resource comment in Elasticsearch');
        }

        return $comment;
    }

    public function delete(string $commentId): void
    {
        $commentId = trim($commentId);
        if ($commentId === '') {
            throw new InvalidArgumentException('Comment id is required');
        }

        $this->indexManager->ensureIndexExists();

        $params = [
            'index' => $this->getIndexName(),
            'id' => $commentId,
            'refresh' => true,
        ];

        try {
            $this->client->delete($params)->asArray();
        } catch (ClientResponseException $exception) {
            if ($exception->getCode() === 404) {
                throw new InvalidArgumentException('Comment not found', 0, $exception);
            }

            throw new RuntimeException(
                sprintf('Failed to delete resource comment from Elasticsearch: %s', $exception->getMessage()),
                0,
                $exception
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Failed to delete resource comment from Elasticsearch: %s', $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    public function findById(string $commentId): ?ItemComment
    {
        $commentId = trim($commentId);
        if ($commentId === '') {
            return null;
        }

        $this->indexManager->ensureIndexExists();

        $params = [
            'index' => $this->getIndexName(),
            'id' => $commentId,
        ];

        try {
            $response = $this->client->get($params)->asArray();
        } catch (ClientResponseException $exception) {
            if ($exception->getCode() === 404) {
                return null;
            }

            throw new RuntimeException(
                sprintf('Failed to get resource comment from Elasticsearch: %s', $exception->getMessage()),
                0,
                $exception
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Failed to get resource comment from Elasticsearch: %s', $exception->getMessage()),
                0,
                $exception
            );
        }

        if (!($response['found'] ?? false)) {
            return null;
        }

        $source = $response['_source'] ?? [];

        return $this->mapSource($source, (string) ($response['_id'] ?? $commentId));
    }

    public function findByResource(string $resourceUri, string $resourceType): array
    {
        $resourceType = ResourceCommentType::assertValid($resourceType);
        $this->indexManager->ensureIndexExists();

        $params = [
            'index' => $this->getIndexName(),
            'body' => [
                'size' => 1000,
                'sort' => [
                    ['createdAt' => ['order' => 'asc']],
                ],
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['resourceUri' => $resourceUri]],
                            ['term' => ['resourceType' => $resourceType]],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->client->search($params)->asArray();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Failed to search resource comments in Elasticsearch: %s', $exception->getMessage()),
                0,
                $exception
            );
        }

        $comments = [];
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $source = $hit['_source'] ?? [];
            $comments[] = $this->mapSource($source, (string) ($hit['_id'] ?? ''));
        }

        return $comments;
    }

    private function getIndexName(): string
    {
        return $this->indexPrefixer->prefix(self::INDEX_NAME);
    }

    /**
     * @return array<string, mixed>
     */
    private function toDocument(ItemComment $comment): array
    {
        return [
            'id' => $comment->getId(),
            'resourceUri' => $comment->getResourceUri(),
            'resourceType' => $comment->getResourceType(),
            'authorId' => $comment->getAuthorId(),
            'authorLabel' => $comment->getAuthorLabel(),
            'body' => $comment->getBody(),
            'createdAt' => $comment->getCreatedAt(),
            'edited' => $comment->isEdited(),
            'resolved' => $comment->isResolved(),
        ];
    }

    /**
     * @param array<string, mixed> $source
     */
    private function mapSource(array $source, string $fallbackId): ItemComment
    {
        return new ItemComment(
            (string) ($source['id'] ?? $fallbackId),
            (string) ($source['resourceUri'] ?? ''),
            (string) ($source['resourceType'] ?? ResourceCommentType::ITEM),
            (string) ($source['authorId'] ?? ''),
            (string) ($source['authorLabel'] ?? ''),
            (string) ($source['body'] ?? ''),
            (string) ($source['createdAt'] ?? ''),
            $this->toBool($source['edited'] ?? false),
            $this->toBool($source['resolved'] ?? false)
        );
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
