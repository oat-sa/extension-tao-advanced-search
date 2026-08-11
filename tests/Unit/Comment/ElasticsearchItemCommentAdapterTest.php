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

namespace oat\taoAdvancedSearch\tests\Unit\Comment;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use oat\taoAdvancedSearch\model\Comment\ElasticsearchItemCommentAdapter;
use oat\taoAdvancedSearch\model\Comment\ItemCommentIndexManager;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use oat\taoItems\model\Comment\ItemComment;
use DG\BypassFinals;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ElasticsearchItemCommentAdapterTest extends TestCase
{
    /** @var Client|MockObject */
    private $client;

    /** @var IndexPrefixer|MockObject */
    private $prefixer;

    /** @var ItemCommentIndexManager|MockObject */
    private $indexManager;

    private ElasticsearchItemCommentAdapter $sut;

    protected function setUp(): void
    {
        BypassFinals::enable();
        $this->client = $this->createMock(Client::class);
        $this->prefixer = $this->createMock(IndexPrefixer::class);
        $this->prefixer->method('prefix')->willReturnCallback(
            static function (string $name): string {
                return 'test-' . $name;
            }
        );
        $this->indexManager = $this->createMock(ItemCommentIndexManager::class);
        $this->indexManager->method('ensureIndexExists')->willReturn('test-item-comments');

        $this->sut = new ElasticsearchItemCommentAdapter(
            $this->client,
            $this->prefixer,
            $this->indexManager
        );
    }

    public function testCreateIndexesDocument(): void
    {
        $comment = new ItemComment(
            'c1',
            'item-1',
            'author',
            'Author',
            'hello',
            '2026-08-03T10:00:00+00:00'
        );

        $response = $this->createMock(Elasticsearch::class);
        $response->method('asArray')->willReturn(['result' => 'created']);

        $this->indexManager->expects($this->once())->method('ensureIndexExists');
        $this->client
            ->expects($this->once())
            ->method('index')
            ->with($this->callback(static function (array $params): bool {
                return $params['index'] === 'test-item-comments'
                    && $params['id'] === 'c1'
                    && $params['body']['body'] === 'hello'
                    && $params['body']['edited'] === false
                    && $params['body']['resolved'] === false;
            }))
            ->willReturn($response);

        $this->assertSame($comment, $this->sut->create($comment));
    }

    public function testCreateWrapsClientIndexFailures(): void
    {
        $comment = new ItemComment(
            'c1',
            'item-1',
            'author',
            'Author',
            'hello',
            '2026-08-03T10:00:00+00:00'
        );
        $previous = new RuntimeException('transport down');

        $this->client
            ->expects($this->once())
            ->method('index')
            ->willThrowException($previous);

        try {
            $this->sut->create($comment);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Failed to index item comment "c1" in Elasticsearch',
                $exception->getMessage()
            );
            $this->assertStringContainsString('transport down', $exception->getMessage());
            $this->assertSame($previous, $exception->getPrevious());
        }
    }

    public function testCreateThrowsWhenIndexResultUnexpected(): void
    {
        $comment = new ItemComment(
            'c1',
            'item-1',
            'author',
            'Author',
            'hello',
            '2026-08-03T10:00:00+00:00'
        );

        $response = $this->createMock(Elasticsearch::class);
        $response->method('asArray')->willReturn(['result' => 'noop']);

        $this->client
            ->expects($this->once())
            ->method('index')
            ->willReturn($response);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to index item comment "c1" in Elasticsearch');

        $this->sut->create($comment);
    }

    public function testFindByItemUriMapsHits(): void
    {
        $response = $this->createMock(Elasticsearch::class);
        $response->method('asArray')->willReturn([
            'hits' => [
                'hits' => [
                    [
                        '_id' => 'c1',
                        '_source' => [
                            'id' => 'c1',
                            'itemUri' => 'item-1',
                            'authorId' => 'author',
                            'authorLabel' => 'Author',
                            'body' => 'hello',
                            'createdAt' => '2026-08-03T10:00:00+00:00',
                            'edited' => true,
                            'resolved' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $this->client
            ->expects($this->once())
            ->method('search')
            ->with($this->callback(static function (array $params): bool {
                return ($params['body']['query']['term']['itemUri'] ?? null) === 'item-1';
            }))
            ->willReturn($response);

        $comments = $this->sut->findByItemUri('item-1');
        $this->assertCount(1, $comments);
        $this->assertSame('hello', $comments[0]->getBody());
        $this->assertTrue($comments[0]->isEdited());
        $this->assertFalse($comments[0]->isResolved());
    }
}
