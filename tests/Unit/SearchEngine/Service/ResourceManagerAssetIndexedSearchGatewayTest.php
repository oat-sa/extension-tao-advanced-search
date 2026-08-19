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
 * Foundation, Inc., 31 Milk St # 960789 Boston, MA 02196 USA.
 *
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine\Service;

use Exception;
use oat\tao\model\accessControl\PermissionCheckerInterface;
use oat\tao\model\media\MediaAsset;
use oat\tao\model\media\MediaBrowser;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Exception\AssetSearchUnavailableException;
use oat\taoAdvancedSearch\model\SearchEngine\SearchResult;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceManagerAssetIndexedSearchGateway;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceManagerAssetSearchQueryBuilder;
use oat\taoItems\model\media\AssetSearchQuery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ResourceManagerAssetIndexedSearchGatewayTest extends TestCase
{
    /** @var ElasticSearch|MockObject */
    private $elasticSearch;

    /** @var ResourceManagerAssetSearchQueryBuilder|MockObject */
    private $queryBuilder;

    /** @var PermissionCheckerInterface|MockObject */
    private $permissionChecker;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var ResourceManagerAssetIndexedSearchGateway */
    private $subject;

    protected function setUp(): void
    {
        $this->elasticSearch = $this->createMock(ElasticSearch::class);
        $this->queryBuilder = $this->createMock(ResourceManagerAssetSearchQueryBuilder::class);
        $this->permissionChecker = $this->createMock(PermissionCheckerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = new ResourceManagerAssetIndexedSearchGateway(
            $this->elasticSearch,
            $this->queryBuilder,
            $this->permissionChecker,
            $this->logger
        );
    }

    public function testIsAvailableReturnsTrueWhenPingSucceeds(): void
    {
        $this->elasticSearch->method('ping')->willReturn(true);

        $this->assertTrue($this->subject->isAvailable());
    }

    public function testIsAvailableReturnsFalseWhenPingFails(): void
    {
        $this->elasticSearch->method('ping')->willThrowException(new Exception('ES down'));
        $this->logger->expects($this->once())->method('warning');

        $this->assertFalse($this->subject->isAvailable());
    }

    public function testSearchReturnsAuthorizedHitsWithAclAwareTotal(): void
    {
        $query = $this->createSearchQuery();
        $mediaSource = $query->getAsset()->getMediaSource();

        $mediaSource->method('getDirectories')->willReturn([
            'path' => 'taomedia://mediamanager/Assets',
            'label' => 'Assets',
            'children' => [],
        ]);

        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);
        $this->elasticSearch->method('searchWithBody')->willReturn(
            new SearchResult(
                [
                    ['id' => 'asset://allowed', 'label' => 'Allowed', 'mime_type' => 'image/png'],
                    ['id' => 'asset://denied', 'label' => 'Denied', 'mime_type' => 'image/png'],
                ],
                2
            )
        );
        $this->permissionChecker->method('hasReadAccess')->willReturnMap([
            ['asset://allowed', true],
            ['asset://denied', false],
        ]);

        $result = $this->subject->search($query);

        $this->assertSame(1, $result['total']);
        $this->assertSame('asset://allowed', $result['items'][0]['uri']);
        $this->assertSame('image/png', $result['items'][0]['mime']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['pageSize']);
    }

    public function testSearchWrapsElasticsearchFailures(): void
    {
        $query = $this->createSearchQuery();
        $mediaSource = $query->getAsset()->getMediaSource();
        $mediaSource->method('getDirectories')->willReturn(['path' => '/', 'label' => 'Assets', 'children' => []]);
        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);
        $this->elasticSearch->method('searchWithBody')->willThrowException(new Exception('search failed'));

        $this->expectException(AssetSearchUnavailableException::class);
        $this->subject->search($query);
    }

    private function createSearchQuery(): AssetSearchQuery
    {
        $mediaSource = $this->createMock(MediaBrowser::class);
        $asset = $this->createMock(MediaAsset::class);
        $asset->method('getMediaSource')->willReturn($mediaSource);
        $asset->method('getMediaIdentifier')->willReturn('/');

        return (new AssetSearchQuery($asset, 'item-uri', 'en-US'))
            ->setQuery('clip')
            ->setPage(1)
            ->setPageSize(10);
    }
}
