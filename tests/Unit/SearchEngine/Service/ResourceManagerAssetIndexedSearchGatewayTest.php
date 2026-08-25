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
        $this->permissionChecker->method('hasReadAccess')->willReturnCallback(
            static function (string $uri): bool {
                return $uri === 'asset://allowed';
            }
        );

        $result = $this->subject->search($query);

        $this->assertSame(1, $result['total']);
        $this->assertSame('asset://allowed', $result['items'][0]['uri']);
        $this->assertSame('image/png', $result['items'][0]['mime']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['pageSize']);
        $this->assertFalse($result['totalIsApproximate']);
    }

    public function testSearchMapsHitWithoutMimeTypeAndArrayMimeField(): void
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
                    [
                        'id' => 'asset://allowed-array-mime',
                        'label' => ['Array Label'],
                        'mime_type' => ['image/png'],
                    ],
                    [
                        'id' => 'asset://allowed-missing-mime',
                        'label' => 'Missing Mime',
                    ],
                    [
                        'id' => 'asset://denied',
                        'label' => 'Denied',
                        'mime_type' => 'image/png',
                    ],
                ],
                3
            )
        );
        $this->permissionChecker->method('hasReadAccess')->willReturnCallback(
            static function (string $uri): bool {
                return $uri !== 'asset://denied';
            }
        );

        $result = $this->subject->search($query);

        $this->assertSame(2, $result['total']);
        $this->assertSame('asset://allowed-array-mime', $result['items'][0]['uri']);
        $this->assertSame('Array Label', $result['items'][0]['label']);
        $this->assertSame('image/png', $result['items'][0]['mime']);
        $this->assertSame('asset://allowed-missing-mime', $result['items'][1]['uri']);
        $this->assertSame('', $result['items'][1]['mime']);
        $this->assertFalse($result['totalIsApproximate']);
    }

    public function testSearchMapsHttpResourceIdToMediaBrowserUri(): void
    {
        $query = $this->createSearchQuery();
        $mediaSource = $query->getAsset()->getMediaSource();

        $mediaSource->method('getDirectories')->willReturn([
            'path' => 'taomedia://mediamanager/Assets',
            'label' => 'Assets',
            'children' => [],
        ]);

        $resourceUri = 'https://backoffice.ngs.test/ontologies/tao.rdf#i6a7ef51ec9e1c';

        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);
        $this->elasticSearch->method('searchWithBody')->willReturn(
            new SearchResult(
                [
                    ['id' => $resourceUri, 'label' => 'Clip', 'mime_type' => 'video/mp4'],
                ],
                1
            )
        );
        $this->permissionChecker->method('hasReadAccess')->willReturn(true);

        $result = $this->subject->search($query);

        $this->assertSame(
            'taomedia://mediamanager/' . \tao_helpers_Uri::encode($resourceUri),
            $result['items'][0]['uri']
        );
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
