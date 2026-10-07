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

use oat\generis\model\data\permission\PermissionInterface;
use oat\oatbox\session\SessionService;
use oat\oatbox\user\User;
use oat\tao\model\media\MediaAsset;
use oat\tao\model\media\MediaBrowser;
use oat\taoItems\model\media\AssetSearchQuery;
use oat\taoAdvancedSearch\model\SearchEngine\Service\AssetSearchTokenizer;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesQueryService;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceManagerAssetSearchQueryBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceQueryBlockSupport;
use oat\taoAdvancedSearch\model\SearchEngine\Specification\UseAclSpecification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ResourceManagerAssetSearchQueryBuilderTest extends TestCase
{
    // coderabbit: ignored — php -l clean; all methods stay inside this test class (brace FP)

    /** @var ResourceManagerAssetSearchQueryBuilder */
    private $subject;

    /** @var SessionService|MockObject */
    private $sessionService;

    /** @var PermissionInterface|MockObject */
    private $permission;

    /** @var UseAclSpecification|MockObject */
    private $useAclSpecification;

    protected function setUp(): void
    {
        $this->sessionService = $this->createMock(SessionService::class);
        $this->sessionService->method('getCurrentUser')->willReturn($this->createMock(User::class));
        $this->permission = $this->createMock(PermissionInterface::class);
        $this->useAclSpecification = $this->createMock(UseAclSpecification::class);
        $this->useAclSpecification->method('isSatisfiedBy')->willReturn(false);

        $this->subject = new ResourceManagerAssetSearchQueryBuilder(
            new NestedAttributesQueryService(),
            new ResourceQueryBlockSupport(),
            $this->sessionService,
            $this->permission,
            $this->useAclSpecification,
            new AssetSearchTokenizer()
        );
    }

    public function testBuildAddsReadAccessFilterWhenAclSpecificationRequiresIt(): void
    {
        $sessionService = $this->createMock(SessionService::class);
        $user = $this->createMock(User::class);
        $user->method('getIdentifier')->willReturn('user-id-1');
        $user->method('getRoles')->willReturn(['role-uri-1']);
        $sessionService->method('getCurrentUser')->willReturn($user);

        $this->useAclSpecification = $this->createMock(UseAclSpecification::class);
        $this->useAclSpecification->method('isSatisfiedBy')->willReturn(true);
        $this->subject = new ResourceManagerAssetSearchQueryBuilder(
            new NestedAttributesQueryService(),
            new ResourceQueryBlockSupport(),
            $sessionService,
            $this->permission,
            $this->useAclSpecification,
            new AssetSearchTokenizer()
        );

        $body = $this->subject->build($this->createQuery(''), '');

        $this->assertTrue($this->subject->lastBuildAppliedAccessControl());
        $aclClause = end($body['query']['bool']['must']);
        $this->assertSame(
            ['user-id-1', 'role-uri-1'],
            $aclClause['terms']['read_access']
        );
    }

    public function testBuildScopesByParentClassUri(): void
    {
        $classUri = 'http://www.tao.lu/Ontologies/TAOMedia.rdf#iFolderClass';
        $body = $this->subject->build($this->createQuery(''), $classUri);

        $scopeClause = $body['query']['bool']['must'][0];
        $this->assertSame(
            [
                ['term' => ['parent_classes.raw' => $classUri]],
                ['match_phrase' => ['parent_classes' => $classUri]],
            ],
            $scopeClause['bool']['should']
        );
    }

    public function testBuildUsesDistinctScopeUrisForSameLabelPaths(): void
    {
        $classUriA = 'http://www.tao.lu/Ontologies/TAOMedia.rdf#iBranchAImages';
        $classUriB = 'http://www.tao.lu/Ontologies/TAOMedia.rdf#iBranchBImages';

        $scopeA = $this->subject->build($this->createQuery(''), $classUriA)['query']['bool']['must'][0];
        $scopeB = $this->subject->build($this->createQuery(''), $classUriB)['query']['bool']['must'][0];

        $this->assertNotSame($scopeA, $scopeB);
        $this->assertSame($classUriA, $scopeA['bool']['should'][0]['term']['parent_classes.raw']);
        $this->assertSame($classUriB, $scopeB['bool']['should'][0]['term']['parent_classes.raw']);
    }

    public function testBuildCombinesUniversalTokensWithAnd(): void
    {
        $body = $this->subject->build($this->createQuery('color grade'), '');

        $tokenClauses = $body['query']['bool']['must'];
        $this->assertCount(2, $tokenClauses);
        $this->assertSame(
            ['value' => 'color', 'case_insensitive' => true],
            $tokenClauses[0]['prefix']['search_tokens']
        );
        $this->assertSame(
            ['value' => 'grade', 'case_insensitive' => true],
            $tokenClauses[1]['prefix']['search_tokens']
        );
    }

    public function testBuildUsesTermForShortUniversalTokens(): void
    {
        $body = $this->subject->build($this->createQuery('ab'), '');

        $tokenClause = $body['query']['bool']['must'][0];
        $this->assertSame(['search_tokens' => 'ab'], $tokenClause['term']);
    }

    public function testBuildUsesPrefixForUniversalTokensOfThreeOrMoreCharacters(): void
    {
        $body = $this->subject->build($this->createQuery('col'), '');

        $tokenClause = $body['query']['bool']['must'][0];
        $this->assertSame(
            ['value' => 'col', 'case_insensitive' => true],
            $tokenClause['prefix']['search_tokens']
        );
    }

    public function testBuildMatchesTrailingTokenInsideFilename(): void
    {
        $body = $this->subject->build($this->createQuery('154'), '');

        $tokenClause = $body['query']['bool']['must'][0];
        $this->assertSame(
            ['value' => '154', 'case_insensitive' => true],
            $tokenClause['prefix']['search_tokens']
        );
    }

    public function testBuildAddsMimeTermsClause(): void
    {
        $query = $this->createQuery('video', ['video/mp4', 'image/png']);
        $body = $this->subject->build($query, '');

        $mimeClause = end($body['query']['bool']['must']);
        $this->assertSame(
            ['video/mp4', 'image/png'],
            $mimeClause['bool']['should'][0]['terms']['mime_type']
        );
        $this->assertSame(
            ['video/mp4', 'image/png'],
            $mimeClause['bool']['should'][1]['terms']['mime_type.keyword']
        );
        $this->assertCount(2, $mimeClause['bool']['should']);
    }

    public function testBuildAddsMetadataClauseWithPropertyUriAndValue(): void
    {
        $propertyUri = 'http://www.tao.lu/Ontologies/TAO.rdf#Keywords';
        $body = $this->subject->build(
            $this->createQuery(''),
            '',
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
            ['value' => 'science', 'case_insensitive' => true],
            $trailing['bool']['must'][1]['prefix']['attributes.search_tokens']
        );
    }

    public function testBuildCombinesMultipleMetadataCriteriaWithAnd(): void
    {
        $body = $this->subject->build(
            $this->createQuery(''),
            '',
            [
                'http://example/Language' => 'http://example/Langja-JP',
                'http://example/label' => '47',
            ]
        );

        $mustClauses = $body['query']['bool']['must'];
        $this->assertCount(2, $mustClauses);

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
            ['attributes.search_tokens' => '47'],
            $labelTrailing['bool']['must'][1]['term']
        );
    }

    public function testBuildCombinesMetadataWithUniversalQueryUsingAnd(): void
    {
        $propertyUri = 'http://www.tao.lu/Ontologies/TAO.rdf#Category';
        $body = $this->subject->build(
            $this->createQuery('diagram'),
            '',
            [$propertyUri => 'Diagram']
        );

        $mustClauses = $body['query']['bool']['must'];
        $this->assertCount(2, $mustClauses);
        $this->assertSame(
            ['value' => 'diagram', 'case_insensitive' => true],
            $mustClauses[0]['prefix']['search_tokens']
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
            ['value' => 'clip', 'case_insensitive' => true],
            $mustClauses[0]['prefix']['search_tokens']
        );
    }

    public function testBuildReturnsNoMatchesForDelimiterOnlyQuery(): void
    {
        $body = $this->subject->build($this->createQuery('---'), '');

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
        throw new \RuntimeException('Unreachable');
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
        throw new \RuntimeException('Unreachable');
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
