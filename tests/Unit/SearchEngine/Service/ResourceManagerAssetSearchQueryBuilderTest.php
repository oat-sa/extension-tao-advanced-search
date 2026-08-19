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

use oat\tao\model\media\MediaAsset;
use oat\tao\model\media\MediaBrowser;
use oat\taoItems\model\media\AssetSearchQuery;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesQueryService;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceManagerAssetSearchQueryBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceQueryBlockSupport;
use PHPUnit\Framework\TestCase;

class ResourceManagerAssetSearchQueryBuilderTest extends TestCase
{
    /** @var ResourceManagerAssetSearchQueryBuilder */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new ResourceManagerAssetSearchQueryBuilder(
            new NestedAttributesQueryService(),
            new ResourceQueryBlockSupport()
        );
    }

    public function testBuildAddsScopePrefixClause(): void
    {
        $body = $this->subject->build($this->createQuery(''), 'Assets/Folder');

        $scopeClause = $body['query']['bool']['must'][0];
        $this->assertSame(
            [
                ['term' => ['location.raw' => 'Assets/Folder']],
                ['prefix' => ['location.raw' => 'Assets/Folder/']],
            ],
            $scopeClause['bool']['should']
        );
    }

    public function testBuildCombinesUniversalTokensWithAnd(): void
    {
        $body = $this->subject->build($this->createQuery('color grade'), 'Assets');

        $tokenClauses = array_slice($body['query']['bool']['must'], 1);
        $this->assertCount(2, $tokenClauses);
        $this->assertSame(['prefix' => ['label.raw' => 'color']], $tokenClauses[0]['bool']['should'][0]);
        $this->assertSame(['prefix' => ['label.raw' => 'grade']], $tokenClauses[1]['bool']['should'][0]);
    }

    public function testBuildAddsMimeTermsClause(): void
    {
        $query = $this->createQuery('video', ['video/mp4', 'image/png']);
        $body = $this->subject->build($query, 'Assets');

        $mimeClause = end($body['query']['bool']['must']);
        $this->assertSame(['video/mp4', 'image/png'], $mimeClause['terms']['type']);
    }

    public function testBuildAddsMetadataPlaceholderClause(): void
    {
        $propertyUri = 'http://www.tao.lu/Ontologies/TAO.rdf#Keywords';
        $body = $this->subject->build(
            $this->createQuery(''),
            'Assets',
            [$propertyUri => 'science']
        );

        $metadataClause = end($body['query']['bool']['must']);
        $this->assertArrayHasKey('bool', $metadataClause);
        $this->assertArrayHasKey('nested', $metadataClause['bool']['should'][1]);
    }

    public function testBuildCombinesMetadataWithUniversalQueryUsingAnd(): void
    {
        $propertyUri = 'http://www.tao.lu/Ontologies/TAO.rdf#Category';
        $body = $this->subject->build(
            $this->createQuery('diagram'),
            'Assets',
            [$propertyUri => 'Diagram']
        );

        $mustClauses = $body['query']['bool']['must'];
        $this->assertCount(3, $mustClauses);
        $this->assertSame(['prefix' => ['label.raw' => 'diagram']], $mustClauses[1]['bool']['should'][0]);
        $this->assertArrayHasKey('nested', end($mustClauses)['bool']['should'][1]);
    }

    private function createQuery(string $text, array $filter = []): AssetSearchQuery
    {
        $mediaSource = $this->createMock(MediaBrowser::class);
        $asset = $this->createMock(MediaAsset::class);
        $asset->method('getMediaSource')->willReturn($mediaSource);

        $query = new AssetSearchQuery($asset, 'item-uri', 'en-US', $filter);
        $query->setQuery($text);

        return $query;
    }
}
