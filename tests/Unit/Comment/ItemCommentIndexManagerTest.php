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

namespace oat\taoAdvancedSearch\tests\Unit\Comment;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Response\Elasticsearch;
use oat\generis\test\ServiceManagerMockTrait;
use oat\taoAdvancedSearch\model\Comment\ItemCommentIndexManager;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use DG\BypassFinals;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ItemCommentIndexManagerTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var Client|MockObject */
    private $client;

    /** @var Indices|MockObject */
    private $indices;

    private ItemCommentIndexManager $sut;

    protected function setUp(): void
    {
        BypassFinals::enable();

        $this->indices = $this->createMock(Indices::class);
        $this->client = $this->createMock(Client::class);
        $this->client->method('indices')->willReturn($this->indices);

        $prefixer = $this->createMock(IndexPrefixer::class);
        $prefixer->method('prefix')->willReturn('test-item-comments');

        $this->sut = new ItemCommentIndexManager();
        $this->sut->setServiceLocator(
            $this->getServiceManagerMock([
                Client::class => $this->client,
                IndexPrefixer::class => $prefixer,
            ])
        );
    }

    public function testEnsureIndexExistsReturnsWhenAlreadyPresent(): void
    {
        $existsResponse = $this->createMock(Elasticsearch::class);
        $existsResponse->method('asBool')->willReturn(true);

        $this->indices->expects($this->once())->method('exists')->willReturn($existsResponse);
        $this->indices->expects($this->never())->method('create');

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
                    && $definition['body']['mappings']['properties']['createdAt']['type'] === 'date';
            }));

        $this->assertSame('test-item-comments', $this->sut->ensureIndexExists());
    }
}
