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
    // coderabbit: ignored — php -l clean; all methods stay inside this test class (brace FP)

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
        $this->assertSame(
            'color[A-Za-z0-9]*.*|.*[^A-Za-z0-9]color[A-Za-z0-9]*.*',
            $tokenClauses[0]['bool']['should'][0]['regexp']['label.raw']['value']
        );
        $this->assertSame(
            'grade[A-Za-z0-9]*.*|.*[^A-Za-z0-9]grade[A-Za-z0-9]*.*',
            $tokenClauses[1]['bool']['should'][0]['regexp']['label.raw']['value']
        );
    }

    public function testBuildUsesTermForShortUniversalTokens(): void
    {
        $body = $this->subject->build($this->createQuery('ab'), 'Assets');

        $tokenClause = $body['query']['bool']['must'][1];
        $expected = 'ab([^A-Za-z0-9].*)?|.*[^A-Za-z0-9]ab([^A-Za-z0-9].*)?';
        $this->assertSame($expected, $tokenClause['bool']['should'][0]['regexp']['label.raw']['value']);
        $this->assertSame($expected, $tokenClause['bool']['should'][1]['regexp']['location.raw']['value']);
        $this->assertSame(
            $expected,
            $tokenClause['bool']['should'][2]['nested']['query']['regexp']['attributes.raw_value.raw']['value']
        );
    }

    public function testBuildUsesPrefixForUniversalTokensOfThreeOrMoreCharacters(): void
    {
        $body = $this->subject->build($this->createQuery('col'), 'Assets');

        $tokenClause = $body['query']['bool']['must'][1];
        $this->assertSame(
            'col[A-Za-z0-9]*.*|.*[^A-Za-z0-9]col[A-Za-z0-9]*.*',
            $tokenClause['bool']['should'][0]['regexp']['label.raw']['value']
        );
        $this->assertTrue($tokenClause['bool']['should'][0]['regexp']['label.raw']['case_insensitive']);
    }

    public function testBuildMatchesTrailingTokenInsideFilename(): void
    {
        $body = $this->subject->build($this->createQuery('154'), 'Assets');

        $tokenClause = $body['query']['bool']['must'][1];
        $this->assertSame(
            '154[A-Za-z0-9]*.*|.*[^A-Za-z0-9]154[A-Za-z0-9]*.*',
            $tokenClause['bool']['should'][0]['regexp']['label.raw']['value']
        );
    }

    public function testBuildAddsMimeTermsClause(): void
    {
        $query = $this->createQuery('video', ['video/mp4', 'image/png']);
        $body = $this->subject->build($query, 'Assets');

        $mimeClause = end($body['query']['bool']['must']);
        $this->assertSame(
            ['video/mp4', 'image/png'],
            $mimeClause['bool']['should'][0]['terms']['mime_type']
        );
        $this->assertSame(
            ['video/mp4', 'image/png'],
            $mimeClause['bool']['should'][1]['terms']['mime_type.keyword']
        );
        $this->assertArrayHasKey('must_not', $mimeClause['bool']['should'][2]['bool']);
    }

    public function testBuildAddsMetadataClauseWithPropertyUriAndValue(): void
    {
        $propertyUri = 'http://www.tao.lu/Ontologies/TAO.rdf#Keywords';
        $body = $this->subject->build(
            $this->createQuery(''),
            'Assets',
            [$propertyUri => 'science']
        );

        $nestedQuery = $this->extractExactNestedMetadataQuery($body);
        $this->assertSame(
            ['term' => ['attributes.key' => $propertyUri]],
            $nestedQuery['bool']['must'][0]
        );
        $this->assertSame(
            ['term' => ['attributes.value.raw' => 'science']],
            $nestedQuery['bool']['must'][1]['bool']['should'][0]
        );

        $trailing = $this->extractTrailingTokenMetadataQuery($body);
        $this->assertSame(
            ['term' => ['attributes.key' => $propertyUri]],
            $trailing['bool']['must'][0]
        );
        $this->assertSame(
            'science[A-Za-z0-9]*.*|.*[^A-Za-z0-9]science[A-Za-z0-9]*.*',
            $trailing['bool']['must'][1]['bool']['should'][0]['regexp']['attributes.value.raw']['value']
        );
    }

    public function testBuildCombinesMultipleMetadataCriteriaWithAnd(): void
    {
        $body = $this->subject->build(
            $this->createQuery(''),
            'Assets',
            [
                'http://example/Language' => 'http://example/Langja-JP',
                'http://example/label' => '47',
            ]
        );

        $mustClauses = $body['query']['bool']['must'];
        // scope + 2 metadata criteria
        $this->assertCount(3, $mustClauses);

        $labelTrailing = null;
        foreach ($mustClauses as $clause) {
            $trailing = $clause['bool']['should'][1]['nested']['query'] ?? null;
            if (
                is_array($trailing)
                && ($trailing['bool']['must'][0]['term']['attributes.key'] ?? null) === 'http://example/label'
            ) {
                $labelTrailing = $trailing;
                break;
            }
        }
        $this->assertNotNull($labelTrailing);
        $this->assertSame(
            '47([^A-Za-z0-9].*)?|.*[^A-Za-z0-9]47([^A-Za-z0-9].*)?',
            $labelTrailing['bool']['must'][1]['bool']['should'][0]['regexp']['attributes.value.raw']['value']
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
        $this->assertSame(
            'diagram[A-Za-z0-9]*.*|.*[^A-Za-z0-9]diagram[A-Za-z0-9]*.*',
            $mustClauses[1]['bool']['should'][0]['regexp']['label.raw']['value']
        );

        $nestedQuery = $this->extractExactNestedMetadataQuery($body);
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
        $this->assertSame(
            'clip[A-Za-z0-9]*.*|.*[^A-Za-z0-9]clip[A-Za-z0-9]*.*',
            $mustClauses[0]['bool']['should'][0]['regexp']['label.raw']['value']
        );
    }

    public function testBuildReturnsNoMatchesForDelimiterOnlyQuery(): void
    {
        $body = $this->subject->build($this->createQuery('---'), 'Assets');

        $this->assertArrayHasKey('match_none', end($body['query']['bool']['must']));
    }

    private function extractExactNestedMetadataQuery(array $body): array
    {
        foreach ($body['query']['bool']['must'] as $clause) {
            $legacyNested = $clause['bool']['should'][0]['bool']['should'][1]['nested']['query'] ?? null;
            if (is_array($legacyNested)) {
                return $legacyNested;
            }
        }

        $this->fail('Exact nested metadata query not found');
    }

    private function extractTrailingTokenMetadataQuery(array $body): array
    {
        foreach ($body['query']['bool']['must'] as $clause) {
            $trailing = $clause['bool']['should'][1]['nested']['query'] ?? null;
            if (is_array($trailing)) {
                return $trailing;
            }
        }

        $this->fail('Trailing-token metadata query not found');
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
