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
use oat\taoAdvancedSearch\model\SearchEngine\Contract\AssetMimeTypeResolverInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\AssetUriEncoderInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoItems\model\media\AssetSearchUnavailableException;
use oat\taoAdvancedSearch\model\SearchEngine\SearchResult;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceManagerAssetIndexedSearchGateway;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceManagerAssetSearchQueryBuilder;
use oat\taoItems\model\media\AssetSearchQuery;
use oat\taoItems\model\media\AssetUpdatedAtNormalizer;
use oat\taoItems\model\media\ResourceUpdatedAtResolver;
use oat\taoMediaManager\model\MediaSource;
use oat\taoMediaManager\model\TaoMediaOntology;
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

    /** @var AssetMimeTypeResolverInterface|MockObject */
    private $mimeTypeResolver;

    /** @var AssetUriEncoderInterface|MockObject */
    private $uriEncoder;

    /** @var ResourceUpdatedAtResolver */
    private $updatedAtResolver;

    /** @var ResourceManagerAssetIndexedSearchGateway */
    private $subject;

    protected function setUp(): void
    {
        $this->elasticSearch = $this->createMock(ElasticSearch::class);
        $this->queryBuilder = $this->createMock(ResourceManagerAssetSearchQueryBuilder::class);
        $this->queryBuilder->method('lastBuildAppliedAccessControl')->willReturn(false);
        $this->permissionChecker = $this->createMock(PermissionCheckerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->mimeTypeResolver = $this->createMock(AssetMimeTypeResolverInterface::class);
        $this->mimeTypeResolver->method('resolve')->willReturn('');
        $this->uriEncoder = $this->createMock(AssetUriEncoderInterface::class);
        $this->uriEncoder->method('encode')->willReturnCallback(static function (string $uri): string {
            return \tao_helpers_Uri::encode($uri);
        });
        $this->updatedAtResolver = $this->createUpdatedAtResolverStub();

        $this->subject = new ResourceManagerAssetIndexedSearchGateway(
            $this->elasticSearch,
            $this->queryBuilder,
            $this->permissionChecker,
            $this->logger,
            $this->mimeTypeResolver,
            $this->uriEncoder,
            $this->updatedAtResolver
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

    public function testBrowseListingScopesBareMediamanagerPathToMediaRootClass(): void
    {
        $rootClassUri = TaoMediaOntology::CLASS_URI_MEDIA_ROOT;
        $mediaSource = $this->createMock(MediaBrowser::class);
        $asset = $this->createMock(MediaAsset::class);
        $asset->method('getMediaSource')->willReturn($mediaSource);
        $asset->method('getMediaIdentifier')->willReturn(MediaSource::SCHEME_NAME);
        $query = (new AssetSearchQuery($asset, 'item-uri', 'en-US'))
            ->setPage(1)
            ->setPageSize(15);

        $this->queryBuilder->expects($this->once())
            ->method('build')
            ->with(
                $query,
                $rootClassUri,
                $query->getMetadataCriteria()
            )
            ->willReturn(['query' => ['bool' => ['must' => []]]]);
        $this->elasticSearch->method('searchWithBody')->willReturn(new SearchResult([], 0));
        $this->permissionChecker->method('hasReadAccess')->willReturn(true);

        $result = $this->subject->search($query);

        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertFalse($result['totalIsApproximate']);
    }

    public function testBrowseListingWithEmptyIndexClampsHighPageToOne(): void
    {
        $mediaSource = $this->createMock(MediaBrowser::class);
        $asset = $this->createMock(MediaAsset::class);
        $asset->method('getMediaSource')->willReturn($mediaSource);
        $asset->method('getMediaIdentifier')->willReturn(MediaSource::SCHEME_NAME);
        $query = (new AssetSearchQuery($asset, 'item-uri', 'en-US'))
            ->setPage(5)
            ->setPageSize(15);

        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);
        $this->elasticSearch->method('searchWithBody')->willReturn(new SearchResult([], 0));
        $this->permissionChecker->method('hasReadAccess')->willReturn(true);

        $result = $this->subject->search($query);

        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame([], $result['items']);
    }

    public function testBrowseListingUsesDirectPaginationWhenAccessControlIsInElasticsearchQuery(): void
    {
        $this->queryBuilder->method('lastBuildAppliedAccessControl')->willReturn(true);

        $mediaSource = $this->createMock(MediaBrowser::class);
        $asset = $this->createMock(MediaAsset::class);
        $asset->method('getMediaSource')->willReturn($mediaSource);
        $asset->method('getMediaIdentifier')->willReturn(MediaSource::SCHEME_NAME);
        $query = (new AssetSearchQuery($asset, 'item-uri', 'en-US'))
            ->setPage(1)
            ->setPageSize(15);

        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);

        $requestedSizes = [];
        $hits = [];
        for ($i = 0; $i < 100; $i++) {
            $hits[] = [
                'id' => 'asset://hit-' . $i,
                'label' => 'Hit ' . $i,
                'mime_type' => 'image/png',
            ];
        }
        $this->elasticSearch->method('searchWithBody')->willReturnCallback(
            static function ($index, array $body) use ($hits, &$requestedSizes): SearchResult {
                $requestedSizes[] = (int)($body['size'] ?? 0);
                $from = (int)($body['from'] ?? 0);
                $size = (int)($body['size'] ?? 0);

                return new SearchResult(array_slice($hits, $from, $size), count($hits));
            }
        );

        $result = $this->subject->search($query);

        $this->assertSame([15], $requestedSizes);
        $this->assertCount(15, $result['items']);
        $this->assertSame(100, $result['total']);
        $this->assertFalse($result['totalIsApproximate']);
    }

    public function testBrowseListingUsesSingleElasticsearchPageWithoutAccessControlInQuery(): void
    {
        $mediaSource = $this->createMock(MediaBrowser::class);
        $asset = $this->createMock(MediaAsset::class);
        $asset->method('getMediaSource')->willReturn($mediaSource);
        $asset->method('getMediaIdentifier')->willReturn(MediaSource::SCHEME_NAME);
        $query = (new AssetSearchQuery($asset, 'item-uri', 'en-US'))
            ->setPage(1)
            ->setPageSize(15);

        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);

        $requestedSizes = [];
        $hits = [];
        for ($i = 0; $i < 100; $i++) {
            $hits[] = [
                'id' => 'asset://hit-' . $i,
                'label' => 'Hit ' . $i,
                'mime_type' => 'image/png',
            ];
        }
        $this->elasticSearch->method('searchWithBody')->willReturnCallback(
            static function ($index, array $body) use ($hits, &$requestedSizes): SearchResult {
                $requestedSizes[] = (int)($body['size'] ?? 0);

                return new SearchResult(array_slice($hits, 0, (int)($body['size'] ?? 0)), count($hits));
            }
        );
        $this->permissionChecker->method('hasReadAccess')->willReturn(true);

        $result = $this->subject->search($query);

        $this->assertSame([15], $requestedSizes);
        $this->assertCount(15, $result['items']);
        $this->assertSame(100, $result['total']);
        $this->assertTrue($result['totalIsApproximate']);
    }

    public function testBrowseListingReportsElasticsearchTotalWhenAccessControlIsNotInQuery(): void
    {
        $mediaSource = $this->createMock(MediaBrowser::class);
        $asset = $this->createMock(MediaAsset::class);
        $asset->method('getMediaSource')->willReturn($mediaSource);
        $asset->method('getMediaIdentifier')->willReturn(MediaSource::SCHEME_NAME);
        $query = (new AssetSearchQuery($asset, 'item-uri', 'en-US'))
            ->setPage(1)
            ->setPageSize(15);

        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);

        $hits = [];
        for ($i = 0; $i < 30; $i++) {
            $hits[] = [
                'id' => 'asset://hit-' . $i,
                'label' => 'Hit ' . $i,
                'mime_type' => 'image/png',
            ];
        }
        $this->elasticSearch->method('searchWithBody')->willReturn(
            new SearchResult($hits, count($hits))
        );
        $this->permissionChecker->method('hasReadAccess')->willReturn(true);

        $result = $this->subject->search($query);

        $this->assertCount(15, $result['items']);
        $this->assertTrue($result['totalIsApproximate']);
        $this->assertSame(30, $result['total']);
    }

    public function testSearchScopesByClassUriFromBrowsePath(): void
    {
        $folderClassUri = 'http://www.tao.lu/Ontologies/TAOMedia.rdf#iNestedFolder';
        $mediaSource = $this->createMock(MediaBrowser::class);
        $asset = $this->createMock(MediaAsset::class);
        $asset->method('getMediaSource')->willReturn($mediaSource);
        $asset->method('getMediaIdentifier')->willReturn(
            MediaSource::SCHEME_NAME . \tao_helpers_Uri::encode($folderClassUri)
        );
        $query = (new AssetSearchQuery($asset, 'item-uri', 'en-US'))
            ->setQuery('clip')
            ->setPage(1)
            ->setPageSize(10);

        $this->queryBuilder->expects($this->once())
            ->method('build')
            ->with(
                $query,
                $folderClassUri,
                $query->getMetadataCriteria()
            )
            ->willReturn(['query' => ['bool' => ['must' => []]]]);
        $this->elasticSearch->method('searchWithBody')->willReturn(new SearchResult([], 0));
        $this->permissionChecker->method('hasReadAccess')->willReturn(true);

        $this->subject->search($query);
    }

    public function testSearchReturnsAuthorizedHitsWithAclAwareTotal(): void
    {
        $this->queryBuilder->method('lastBuildAppliedAccessControl')->willReturn(true);

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
                ],
                1
            )
        );
        $this->permissionChecker->expects($this->never())->method('hasReadAccess');

        $result = $this->subject->search($query);

        $this->assertSame(1, $result['total']);
        $this->assertSame('asset://allowed', $result['items'][0]['uri']);
        $this->assertSame('image/png', $result['items'][0]['mime']);
        $this->assertNotEmpty($result['items'][0]['updatedAt']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['pageSize']);
        $this->assertFalse($result['totalIsApproximate']);
    }

    public function testSearchMapsHitWithoutMimeTypeAndArrayMimeField(): void
    {
        $this->queryBuilder->method('lastBuildAppliedAccessControl')->willReturn(true);

        $this->mimeTypeResolver = $this->createMock(AssetMimeTypeResolverInterface::class);
        $this->mimeTypeResolver->expects($this->once())
            ->method('resolve')
            ->with('asset://allowed-missing-mime')
            ->willReturn('');
        $this->subject = new ResourceManagerAssetIndexedSearchGateway(
            $this->elasticSearch,
            $this->queryBuilder,
            $this->permissionChecker,
            $this->logger,
            $this->mimeTypeResolver,
            $this->uriEncoder,
            $this->updatedAtResolver
        );

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
                ],
                2
            )
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

    public function testSearchNormalizesUpdatedAtFromUnixTimestamp(): void
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
                        'id' => 'asset://with-ts',
                        'label' => 'With timestamp',
                        'mime_type' => 'image/png',
                        'updated_at' => '1785578400',
                    ],
                ],
                1
            )
        );
        $this->permissionChecker->method('hasReadAccess')->willReturn(true);

        $result = $this->subject->search($query);

        $this->assertSame('2026-08-01T10:00:00Z', $result['items'][0]['updatedAt']);
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

    public function testSearchPaginatesAuthorizedItemsAfterAclPostFilter(): void
    {
        $this->queryBuilder->method('lastBuildAppliedAccessControl')->willReturn(true);

        $query = $this->createSearchQuery()->setPage(2)->setPageSize(1);
        $mediaSource = $query->getAsset()->getMediaSource();
        $mediaSource->method('getDirectories')->willReturn([
            'path' => 'taomedia://mediamanager/Assets',
            'label' => 'Assets',
            'children' => [],
        ]);

        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);
        $this->elasticSearch->method('searchWithBody')->willReturnCallback(
            static function ($index, array $body): SearchResult {
                $from = (int)($body['from'] ?? 0);
                $hits = [
                    ['id' => 'asset://alpha', 'label' => 'Alpha', 'mime_type' => 'image/png'],
                    ['id' => 'asset://beta', 'label' => 'Beta', 'mime_type' => 'image/png'],
                ];

                return new SearchResult(array_slice($hits, $from, (int)($body['size'] ?? 1)), 2);
            }
        );

        $result = $this->subject->search($query);

        $this->assertSame(2, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(1, $result['pageSize']);
        $this->assertSame('asset://beta', $result['items'][0]['uri']);
        $this->assertFalse($result['totalIsApproximate']);
    }

    public function testSearchMarksTotalApproximateWhenScanBudgetExceeded(): void
    {
        $query = $this->createSearchQuery();
        $mediaSource = $query->getAsset()->getMediaSource();
        $mediaSource->method('getDirectories')->willReturn([
            'path' => 'taomedia://mediamanager/Assets',
            'label' => 'Assets',
            'children' => [],
        ]);

        $allHits = [];
        for ($i = 0; $i < 2500; $i++) {
            $allHits[] = [
                'id' => 'asset://hit-' . $i,
                'label' => 'Hit ' . $i,
                'mime_type' => 'image/png',
            ];
        }

        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);
        $this->elasticSearch->method('searchWithBody')->willReturnCallback(
            static function ($index, array $body) use ($allHits): SearchResult {
                $from = (int)($body['from'] ?? 0);
                $size = (int)($body['size'] ?? 10);

                return new SearchResult(array_slice($allHits, $from, $size), count($allHits));
            }
        );
        $this->permissionChecker->method('hasReadAccess')->willReturnCallback(
            static function (string $uri): bool {
                return $uri !== 'asset://denied-mid-scan';
            }
        );

        $result = $this->subject->search($query);

        $this->assertTrue($result['totalIsApproximate']);
        $this->assertSame(2000, $result['total']);
        $this->assertCount(10, $result['items']);
    }

    public function testSearchSkipsHitsWithIndexedMimeOutsideFilterBeforeOntologyLookup(): void
    {
        $this->mimeTypeResolver = $this->createMock(AssetMimeTypeResolverInterface::class);
        $this->mimeTypeResolver->expects($this->never())->method('resolve');
        $this->subject = new ResourceManagerAssetIndexedSearchGateway(
            $this->elasticSearch,
            $this->queryBuilder,
            $this->permissionChecker,
            $this->logger,
            $this->mimeTypeResolver,
            $this->uriEncoder,
            $this->updatedAtResolver
        );

        $mediaSource = $this->createMock(MediaBrowser::class);
        $asset = $this->createMock(MediaAsset::class);
        $asset->method('getMediaSource')->willReturn($mediaSource);
        $asset->method('getMediaIdentifier')->willReturn('/');
        $query = (new AssetSearchQuery($asset, 'item-uri', 'en-US', ['image/png']))
            ->setQuery('clip')
            ->setPage(1)
            ->setPageSize(10);
        $mediaSource->method('getDirectories')->willReturn([
            'path' => 'taomedia://mediamanager/Assets',
            'label' => 'Assets',
            'children' => [],
        ]);

        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);
        $this->elasticSearch->method('searchWithBody')->willReturn(
            new SearchResult(
                [
                    ['id' => 'asset://video', 'label' => 'Video', 'mime_type' => 'video/mp4'],
                    ['id' => 'asset://image', 'label' => 'Image', 'mime_type' => 'image/png'],
                ],
                2
            )
        );
        $this->permissionChecker->method('hasReadAccess')->willReturn(true);

        $result = $this->subject->search($query);

        $this->assertSame(1, $result['total']);
        $this->assertSame('asset://image', $result['items'][0]['uri']);
    }

    public function testSearchReportsConsistentTotalAcrossPagesWithMultipleBatches(): void
    {
        $this->queryBuilder->method('lastBuildAppliedAccessControl')->willReturn(true);

        $totalEsHits = 1000;
        $pageSize = 10;
        $allHits = [];
        for ($i = 0; $i < $totalEsHits; $i++) {
            $allHits[] = [
                'id' => 'asset://hit-' . $i,
                'label' => 'Hit ' . $i,
                'mime_type' => 'image/png',
            ];
        }

        $query = $this->createSearchQuery();
        $mediaSource = $query->getAsset()->getMediaSource();
        $mediaSource->method('getDirectories')->willReturn([
            'path' => 'taomedia://mediamanager/Assets',
            'label' => 'Assets',
            'children' => [],
        ]);

        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);
        $this->elasticSearch->method('searchWithBody')->willReturnCallback(
            static function ($index, array $body) use ($allHits, $totalEsHits): SearchResult {
                $from = (int)($body['from'] ?? 0);
                $size = (int)($body['size'] ?? 10);

                return new SearchResult(array_slice($allHits, $from, $size), $totalEsHits);
            }
        );
        $this->permissionChecker->method('hasReadAccess')->willReturn(true);

        $pageOne = $this->subject->search($query->setPage(1)->setPageSize($pageSize));
        $pageTwo = $this->subject->search($query->setPage(2)->setPageSize($pageSize));

        $this->assertSame($totalEsHits, $pageOne['total']);
        $this->assertFalse($pageOne['totalIsApproximate']);
        $this->assertSame($totalEsHits, $pageTwo['total']);
        $this->assertFalse($pageTwo['totalIsApproximate']);
        $this->assertSame('asset://hit-10', $pageTwo['items'][0]['uri']);
    }

    public function testSearchMapsOnlyCurrentPageHits(): void
    {
        $this->queryBuilder->method('lastBuildAppliedAccessControl')->willReturn(true);

        $this->updatedAtResolver = $this->createMock(ResourceUpdatedAtResolver::class);
        $this->updatedAtResolver->expects($this->exactly(10))
            ->method('resolve')
            ->willReturn('2026-01-01T00:00:00Z');
        $this->subject = new ResourceManagerAssetIndexedSearchGateway(
            $this->elasticSearch,
            $this->queryBuilder,
            $this->permissionChecker,
            $this->logger,
            $this->mimeTypeResolver,
            $this->uriEncoder,
            $this->updatedAtResolver
        );

        $hits = [];
        for ($i = 0; $i < 10; $i++) {
            $hits[] = [
                'id' => 'asset://hit-' . $i,
                'label' => 'Hit ' . $i,
                'mime_type' => 'image/png',
            ];
        }

        $query = $this->createSearchQuery()->setPage(1)->setPageSize(10);
        $mediaSource = $query->getAsset()->getMediaSource();
        $mediaSource->method('getDirectories')->willReturn([
            'path' => 'taomedia://mediamanager/Assets',
            'label' => 'Assets',
            'children' => [],
        ]);

        $this->queryBuilder->method('build')->willReturn(['query' => ['bool' => ['must' => []]]]);
        $this->elasticSearch->method('searchWithBody')->willReturn(
            new SearchResult($hits, 100)
        );

        $result = $this->subject->search($query);

        $this->assertSame(100, $result['total']);
        $this->assertCount(10, $result['items']);
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

    /**
     * @return ResourceUpdatedAtResolver|MockObject
     */
    private function createUpdatedAtResolverStub()
    {
        $resolver = $this->createMock(ResourceUpdatedAtResolver::class);
        $resolver->method('resolve')->willReturnCallback(
            static function ($indexedValue, string $resourceUri): string {
                return AssetUpdatedAtNormalizer::normalize($indexedValue) ?? '1970-01-01T00:00:00Z';
            }
        );

        return $resolver;
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
