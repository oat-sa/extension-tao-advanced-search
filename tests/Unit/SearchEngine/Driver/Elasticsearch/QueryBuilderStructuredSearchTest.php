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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine\Driver\Elasticsearch;

use oat\generis\model\data\permission\PermissionInterface;
use oat\generis\model\data\permission\ReverseRightLookupInterface;
use oat\generis\test\MockObject;
use oat\oatbox\log\LoggerService;
use oat\oatbox\session\SessionService;
use oat\oatbox\user\User;
use oat\tao\model\featureFlag\FeatureFlagCheckerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\QueryBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use oat\taoAdvancedSearch\model\SearchEngine\Service\LegacyResourceQueryConditionsBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesFeature;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesIndexResolver;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesQueryService;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceQueryBlockSupport;
use oat\taoAdvancedSearch\model\SearchEngine\Service\StructuredResourceSearchQueryBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Specification\UseAclSpecification;
use PHPUnit\Framework\TestCase;

/**
 * Structured Elasticsearch DSL when nested attributes are enabled (flag off, items index).
 *
 * @see QueryBuilderTest for legacy query_string expectations (master parity).
 */
class QueryBuilderStructuredSearchTest extends TestCase
{
    private const FIXTURES_PATH = __DIR__ . '/../../../../resources/query-builder-structured-expected-bodies.json';

    /** @var QueryBuilder */
    private $subject;

    /** @var SessionService|MockObject */
    private $sessionServiceMock;

    /** @var PermissionInterface|MockObject */
    private $permissionMock;

    /** @var LoggerService|MockObject */
    private $loggerService;

    /** @var UseAclSpecification|MockObject */
    private $useAclSpecification;

    /** @var IndexPrefixer|MockObject */
    private $prefixer;

    /** @var User|MockObject */
    private $user;

    protected function setUp(): void
    {
        $this->sessionServiceMock = $this->createMock(SessionService::class);
        $this->permissionMock = $this->createMock(PermissionMock::class);
        $this->loggerService = $this->createMock(LoggerService::class);
        $this->user = $this->createMock(User::class);
        $this->useAclSpecification = $this->createMock(UseAclSpecification::class);
        $this->prefixer = $this->createMock(IndexPrefixer::class);

        $this->subject = $this->createQueryBuilderWithNestedAttributesEnabled();

        $this->sessionServiceMock
            ->expects($this->any())
            ->method('getCurrentUser')
            ->willReturn($this->user);

        $this->prefixer
            ->expects($this->any())
            ->method('prefix')
            ->willReturnArgument(0);
    }

    /**
     * @dataProvider structuredQueriesWithAccessControl
     */
    public function testGetSearchParamsWithAccessControl(string $queryString, string $body): void
    {
        $this->createAccessControlMock(true);

        $this->assertSame(
            [
                'index' => 'items',
                'size' => 10,
                'from' => 0,
                'client' => [
                    'ignore' => 404,
                ],
                'body' => $body,
            ],
            $this->subject->getSearchParams($queryString, 'items', 0, 10, '_id', 'DESC')
        );
    }

    public function structuredQueriesWithAccessControl(): array
    {
        return self::fixtureCases('acl');
    }

    /**
     * @dataProvider structuredQueriesWithoutAccessControl
     */
    public function testGetSearchParamsWithoutAccessControl(string $queryString, string $body): void
    {
        $this->createAccessControlMock(false);

        $this->assertSame(
            [
                'index' => 'items',
                'size' => 10,
                'from' => 0,
                'client' => [
                    'ignore' => 404,
                ],
                'body' => $body,
            ],
            $this->subject->getSearchParams($queryString, 'items', 0, 10, '_id', 'DESC')
        );
    }

    public function structuredQueriesWithoutAccessControl(): array
    {
        return self::fixtureCases('noacl');
    }

    public function testRegenerateStructuredQueryBuilderFixtures(): void
    {
        if (!getenv('REGENERATE_QUERY_BUILDER_FIXTURES')) {
            $this->markTestSkipped('Set REGENERATE_QUERY_BUILDER_FIXTURES=1 to regenerate fixtures.');
        }

        $fixtures = ['acl' => [], 'noacl' => []];

        foreach (self::fixtureCases('acl') as $label => [$queryString, $_]) {
            $this->createAccessControlMock(true);
            $params = $this->subject->getSearchParams($queryString, 'items', 0, 10, '_id', 'DESC');
            $fixtures['acl'][$queryString] = $params['body'];
        }

        foreach (self::fixtureCases('noacl') as $label => [$queryString, $_]) {
            $this->createAccessControlMock(false);
            $params = $this->subject->getSearchParams($queryString, 'items', 0, 10, '_id', 'DESC');
            $fixtures['noacl'][$queryString] = $params['body'];
        }

        file_put_contents(
            self::FIXTURES_PATH,
            json_encode($fixtures, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        $this->addToAssertionCount(1);
    }

    private function createQueryBuilderWithNestedAttributesEnabled(): QueryBuilder
    {
        $featureFlagChecker = $this->createMock(FeatureFlagCheckerInterface::class);
        $featureFlagChecker
            ->method('isEnabled')
            ->with(NestedAttributesFeature::FEATURE_FLAG_DISABLE_NESTED_ATTRIBUTES)
            ->willReturn(false);

        $blockSupport = new ResourceQueryBlockSupport();

        return new QueryBuilder(
            $this->loggerService,
            $this->permissionMock,
            $this->sessionServiceMock,
            $this->prefixer,
            $this->useAclSpecification,
            new NestedAttributesFeature($featureFlagChecker, new NestedAttributesIndexResolver()),
            new LegacyResourceQueryConditionsBuilder($blockSupport),
            new StructuredResourceSearchQueryBuilder($blockSupport, new NestedAttributesQueryService()),
            $blockSupport
        );
    }

    private function createAccessControlMock(bool $includeAccessControl): void
    {
        $this->useAclSpecification
            ->method('isSatisfiedBy')
            ->willReturn($includeAccessControl);

        $this->user
            ->expects($this->any())
            ->method('getIdentifier')
            ->willReturn('https://tao.docker.localhost/ontologies/tao.rdf#i5f64514f1c36110793759fc28c0105b');

        $this->user
            ->expects($this->any())
            ->method('getRoles')
            ->willReturn(
                [
                    'http://www.tao.lu/Ontologies/TAOItem.rdf#BackOfficeRole',
                    'http://www.tao.lu/Ontologies/TAOItem.rdf#ItemsManagerRole',
                ]
            );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private static function fixtureCases(string $section): array
    {
        $fixtures = self::loadStructuredFixtures();
        $cases = [];

        foreach ($fixtures[$section] as $queryString => $body) {
            $cases[$queryString] = [$queryString, $body];
        }

        return $cases;
    }

    /**
     * @return array{acl: array<string, string>, noacl: array<string, string>}
     */
    private static function loadStructuredFixtures(): array
    {
        $json = file_get_contents(self::FIXTURES_PATH);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
