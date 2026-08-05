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
use Elastic\Elasticsearch\Endpoints\Cluster;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Response\Elasticsearch;
use oat\taoAdvancedSearch\model\Comment\ItemCommentIndexManager;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use DG\BypassFinals;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ItemCommentIndexManagerTest extends TestCase
{
    /** @var Client|MockObject */
    private $client;

    /** @var Indices|MockObject */
    private $indices;

    /** @var Cluster|MockObject */
    private $cluster;

    private ItemCommentIndexManager $sut;

    protected function setUp(): void
    {
        BypassFinals::enable();

        $this->indices = $this->createMock(Indices::class);
        $this->cluster = $this->createMock(Cluster::class);
        $this->client = $this->createMock(Client::class);
        $this->client->method('indices')->willReturn($this->indices);
        $this->client->method('cluster')->willReturn($this->cluster);

        $prefixer = $this->createMock(IndexPrefixer::class);
        $prefixer->method('prefix')->willReturn('test-item-comments');

        $this->sut = new ItemCommentIndexManager($this->client, $prefixer);
    }

    public function testEnsureIndexExistsReturnsWhenAlreadyPresentAndHealthy(): void
    {
        $existsResponse = $this->createMock(Elasticsearch::class);
        $existsResponse->method('asBool')->willReturn(true);
        $this->indices->expects($this->once())->method('exists')->willReturn($existsResponse);
        $this->indices->expects($this->never())->method('create');
        $this->mockHealthyIndex();

        $this->assertSame('test-item-comments', $this->sut->ensureIndexExists());
    }

    public function testEnsureIndexExistsCreatesMissingIndex(): void
    {
        $existsResponse = $this->createMock(Elasticsearch::class);
        $existsResponse->method('asBool')->willReturn(false);

        $this->indices->expects($this->once())->method('exists')->willReturn($existsResponse);
        $this->indices
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(static function (array $definition): bool {
                return $definition['index'] === 'test-item-comments'
                    && isset($definition['body']['mappings']['properties']['itemUri'])
                    && $definition['body']['mappings']['properties']['itemUri']['type'] === 'keyword'
                    && $definition['body']['mappings']['properties']['createdAt']['type'] === 'date'
                    && $definition['body']['settings']['index']['number_of_replicas'] === '0';
            }));
        $this->mockHealthyIndex();

        $this->assertSame('test-item-comments', $this->sut->ensureIndexExists());
    }

    public function testEnsureIndexExistsThrowsWhenIndexIsRed(): void
    {
        $existsResponse = $this->createMock(Elasticsearch::class);
        $existsResponse->method('asBool')->willReturn(true);
        $this->indices->method('exists')->willReturn($existsResponse);

        $healthResponse = $this->createMock(Elasticsearch::class);
        $healthResponse->method('asArray')->willReturn(['status' => 'red']);
        $this->cluster->method('health')->willReturn($healthResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/red and cannot accept/');
        $this->sut->ensureIndexExists();
    }

    private function mockHealthyIndex(): void
    {
        $healthResponse = $this->createMock(Elasticsearch::class);
        $healthResponse->method('asArray')->willReturn(['status' => 'yellow']);
        $this->cluster->expects($this->once())->method('health')->willReturn($healthResponse);
    }
}
