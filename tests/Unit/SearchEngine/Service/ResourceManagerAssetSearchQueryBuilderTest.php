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
        $this->assertSame(['video/mp4', 'image/png'], $mimeClause['terms']['mime_type']);
    }

    public function testBuildAddsMetadataClauseWithPropertyUriAndValue(): void
    {
        $propertyUri = 'http://www.tao.lu/Ontologies/TAO.rdf#Keywords';
        $body = $this->subject->build(
            $this->createQuery(''),
            'Assets',
            [$propertyUri => 'science']
        );

        $nestedQuery = $this->extractNestedMetadataQuery($body);
        $this->assertSame(
            ['term' => ['attributes.key' => $propertyUri]],
            $nestedQuery['bool']['must'][0]
        );
        $this->assertSame(
            ['term' => ['attributes.value.raw' => 'science']],
            $nestedQuery['bool']['must'][1]['bool']['should'][0]
        );
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

        $nestedQuery = $this->extractNestedMetadataQuery($body);
        $this->assertSame(['term' => ['attributes.key' => $propertyUri]], $nestedQuery['bool']['must'][0]);
        $this->assertSame(
            ['term' => ['attributes.value.raw' => 'Diagram']],
            $nestedQuery['bool']['must'][1]['bool']['should'][0]
        );
    }

    public function testBuildIgnoresInvalidMetadataCriteria(): void
    {
        $body = $this->subject->build(
            $this->createQuery('clip'),
            '',
            [
                123 => 'science',
                'http://example.com/property' => '',
            ]
        );

        $mustClauses = $body['query']['bool']['must'];
        $this->assertCount(1, $mustClauses);
        $this->assertSame(['prefix' => ['label.raw' => 'clip']], $mustClauses[0]['bool']['should'][0]);
    }

    public function testBuildReturnsNoMatchesForDelimiterOnlyQuery(): void
    {
        $body = $this->subject->build($this->createQuery('---'), 'Assets');

        $this->assertArrayHasKey('match_none', end($body['query']['bool']['must']));
    }

    private function extractNestedMetadataQuery(array $body): array
    {
        foreach ($body['query']['bool']['must'] as $clause) {
            if (isset($clause['bool']['should'][1]['nested']['query'])) {
                return $clause['bool']['should'][1]['nested']['query'];
            }
        }

        $this->fail('Nested metadata query not found');
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
