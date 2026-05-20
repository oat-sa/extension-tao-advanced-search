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
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesFeature;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesIndexResolver;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesQueryService;
use oat\taoAdvancedSearch\model\SearchEngine\Specification\UseAclSpecification;
use PHPUnit\Framework\TestCase;

interface PermissionMock extends PermissionInterface, ReverseRightLookupInterface
{
}

class QueryBuilderTest extends TestCase
{
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

    /** @var bool Whether ACL read_access filter is applied (matches mock behaviour). */
    private $accessControlEnabled = false;

    protected function setUp(): void
    {
        $this->sessionServiceMock = $this->createMock(SessionService::class);
        $this->permissionMock = $this->createMock(PermissionMock::class);
        $this->loggerService = $this->createMock(LoggerService::class);
        $this->user = $this->createMock(User::class);
        $this->useAclSpecification = $this->createMock(UseAclSpecification::class);
        $this->prefixer = $this->createMock(IndexPrefixer::class);
        $featureFlagChecker = $this->createMock(FeatureFlagCheckerInterface::class);
        $featureFlagChecker
            ->method('isEnabled')
            ->with(NestedAttributesFeature::FEATURE_FLAG_DISABLE_NESTED_ATTRIBUTES)
            ->willReturn(false);

        $nestedAttributesQueryService = new NestedAttributesQueryService(
            new NestedAttributesFeature(
                $featureFlagChecker,
                new NestedAttributesIndexResolver()
            )
        );

        $this->useAclSpecification
            ->method('isSatisfiedBy')
            ->willReturnCallback(function (): bool {
                return $this->accessControlEnabled;
            });

        $this->subject = new QueryBuilder(
            $this->loggerService,
            $this->permissionMock,
            $this->sessionServiceMock,
            $this->prefixer,
            $this->useAclSpecification,
            $nestedAttributesQueryService
        );

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
     * @dataProvider queryResultsWithAccessControl
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
                    'ignore' => 404
                ],
                'body' => $body,
            ],
            $this->subject->getSearchParams($queryString, 'items', 0, 10, '_id', 'DESC')
        );
    }

    public function queryResultsWithAccessControl(): array
    {
        // phpcs:disable Generic.Files.LineLength.TooLong -- embedded full JSON bodies for assertSame
        return [
            'with user access control and role access control' => [
                'test',
                trim(<<<'JSON'
{"query":{"bool":{"must":[{"query_string":{"default_operator":"AND","query":"(read_access:(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR \"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR \"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#ItemsManagerRole\"))"}},{"bool":{"should":[{"query_string":{"default_operator":"AND","query":"(\"test\")"}},{"nested":{"path":"attributes","query":{"bool":{"should":[{"term":{"attributes.value.raw":"test"}},{"match":{"attributes.raw_value":{"query":"test","operator":"and"}}},{"term":{"attributes.raw_value.raw":"test"}}],"minimum_should_match":1}}}}],"minimum_should_match":1}}]}},"sort":{"_id":{"order":"DESC","missing":"_last","unmapped_type":"long"},"label.raw":{"order":"DESC","missing":"_last","unmapped_type":"long"}}}
JSON
                ),
            ],
            'Simple query' => [
                'test',
                trim(<<<'JSON'
{"query":{"bool":{"must":[{"query_string":{"default_operator":"AND","query":"(read_access:(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR \"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR \"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#ItemsManagerRole\"))"}},{"bool":{"should":[{"query_string":{"default_operator":"AND","query":"(\"test\")"}},{"nested":{"path":"attributes","query":{"bool":{"should":[{"term":{"attributes.value.raw":"test"}},{"match":{"attributes.raw_value":{"query":"test","operator":"and"}}},{"term":{"attributes.raw_value.raw":"test"}}],"minimum_should_match":1}}}}],"minimum_should_match":1}}]}},"sort":{"_id":{"order":"DESC","missing":"_last","unmapped_type":"long"},"label.raw":{"order":"DESC","missing":"_last","unmapped_type":"long"}}}
JSON
                ),
            ],
            'Query specific field' => [
                'label:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\\"test\\") ' .
                'AND (read_access:(\\"https:\\/\\/tao.docker.localhost\\/' .
                'ontologies\\/tao.rdf#i5f64514f1c36110793759fc28c0105b\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#BackOfficeRole\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#ItemsManagerRole\\"))"}},' .
                '"sort":{"_id":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"long"},"label.raw":{"order":"DESC","missing":"_last","unmapped_type":"long"}}}'
            ],
            'Query specific field (variating case)' => [
                'LaBeL:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\\"test\\") ' .
                'AND (read_access:(\\"https:\\/\\/tao.docker.localhost\\/' .
                'ontologies\\/tao.rdf#i5f64514f1c36110793759fc28c0105b\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#BackOfficeRole\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#ItemsManagerRole\\"))"}},' .
                '"sort":{"_id":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"long"},"label.raw":{"order":"DESC","missing":"_last","unmapped_type":"long"}}}'
            ],
            'Query custom field (using underscore)' => [
                'custom_field:test',
                self::expectedBodyAcl('custom_field:test'),
            ],
            'Query custom field (using dash)' => [
                'custom_field:test',
                self::expectedBodyAcl('custom_field:test'),
            ],
            'Query custom field (using space)' => [
                'custom field:test',
                self::expectedBodyAcl('custom field:test'),
            ],
            'Query logic operator (Uppercase)' => [
                'label:test AND custom_field:test',
                self::expectedBodyAcl('label:test AND custom_field:test'),
            ],
            'Query logic operator (Lowercase)' => [
                'label:test and custom_field:test',
                self::expectedBodyAcl('label:test and custom_field:test'),
            ],
            'Query logic operator (Mixed)' => [
                'label:test aNd custom_field:test',
                self::expectedBodyAcl('label:test aNd custom_field:test'),
            ],
            'Query using OR logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_OR custom_field:test1 ',
                self::expectedBodyAcl('label:test AND custom_field:test LOGIC_OR custom_field:test1 '),
            ],
            'Query using AND logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_AND custom_field:test1 ',
                self::expectedBodyAcl('label:test AND custom_field:test LOGIC_AND custom_field:test1 '),
            ],
            'Query using NOT logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_NOT custom_field:test1 ',
                self::expectedBodyAcl('label:test AND custom_field:test LOGIC_NOT custom_field:test1 '),
            ],
            'Query URIs' => [
                'https://test-act.docker.localhost/ontologies/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a',
                trim(<<<'JSON'
{"query":{"bool":{"must":[{"query_string":{"default_operator":"AND","query":"(read_access:(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR \"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR \"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#ItemsManagerRole\"))"}},{"bool":{"should":[{"query_string":{"default_operator":"AND","query":"(\"https:\/\/test-act.docker.localhost\/ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a\")"}},{"nested":{"path":"attributes","query":{"bool":{"should":[{"term":{"attributes.value.raw":"https:\/\/test-act.docker.localhost\/ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a"}},{"match":{"attributes.raw_value":{"query":"https:\/\/test-act.docker.localhost\/ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a","operator":"and"}}},{"term":{"attributes.raw_value.raw":"https:\/\/test-act.docker.localhost\/ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a"}}],"minimum_should_match":1}}}}],"minimum_should_match":1}}]}},"sort":{"_id":{"order":"DESC","missing":"_last","unmapped_type":"long"},"label.raw":{"order":"DESC","missing":"_last","unmapped_type":"long"}}}
JSON
                ),
            ],
            'Query Field with URI' => [
                'delivery: https://test-act.docker.localhost/ontologies/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a',
                '{"query":{"query_string":{"default_operator":"AND","query"' .
                ':"(delivery:\"https:\/\/test-act.docker.localhost\/' .
                'ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a\") AND (read_access:' .
                '(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#' .
                'ItemsManagerRole\"))"}},"sort":{"_id":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"long"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"long"}}}'
            ],
            'Query term with a backslash' => [
                'some\ term',
                trim(<<<'JSON'
{"query":{"bool":{"must":[{"query_string":{"default_operator":"AND","query":"(read_access:(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR \"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR \"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#ItemsManagerRole\"))"}},{"bool":{"should":[{"query_string":{"default_operator":"AND","query":"(\"some\\\\ term\")"}},{"nested":{"path":"attributes","query":{"bool":{"should":[{"term":{"attributes.value.raw":"some\\\\ term"}},{"match":{"attributes.raw_value":{"query":"some\\\\ term","operator":"and"}}},{"term":{"attributes.raw_value.raw":"some\\\\ term"}}],"minimum_should_match":1}}}}],"minimum_should_match":1}}]}},"sort":{"_id":{"order":"DESC","missing":"_last","unmapped_type":"long"},"label.raw":{"order":"DESC","missing":"_last","unmapped_type":"long"}}}
JSON
                ),
            ],
        ];
        // phpcs:enable Generic.Files.LineLength.TooLong
    }

    /**
     * @dataProvider queryResultsWithoutAccessControl
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
                    'ignore' => 404
                ],
                'body' => $body,
            ],
            $this->subject->getSearchParams($queryString, 'items', 0, 10, '_id', 'DESC')
        );
    }

    public function queryResultsWithoutAccessControl(): array
    {
        // phpcs:disable Generic.Files.LineLength.TooLong -- embedded full JSON bodies for assertSame
        return [
            'Simple query' => [
                'test',
                trim(<<<'JSON'
{"query":{"bool":{"must":[{"bool":{"should":[{"query_string":{"default_operator":"AND","query":"(\"test\")"}},{"nested":{"path":"attributes","query":{"bool":{"should":[{"term":{"attributes.value.raw":"test"}},{"match":{"attributes.raw_value":{"query":"test","operator":"and"}}},{"term":{"attributes.raw_value.raw":"test"}}],"minimum_should_match":1}}}}],"minimum_should_match":1}}]}},"sort":{"_id":{"order":"DESC","missing":"_last","unmapped_type":"long"},"label.raw":{"order":"DESC","missing":"_last","unmapped_type":"long"}}}
JSON
                ),
            ],
            'Query specific field' => [
                'label:test',
                '{"query":{"query_string":{"default_operator":"AND","query":' .
                '"(label:\"test\")"}},"sort":{"_id":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"long"},"label.raw":' .
                '{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"long"}}}'
            ],
            'Query specific field (variating case)' => [
                'LaBeL:test',
                '{"query":{"query_string":{"default_operator":"AND","query"' .
                ':"(label:\"test\")"}},"sort":{"_id":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"long"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"long"}}}'
            ],
            'Query custom field (using underscore)' => [
                'custom_field:test',
                self::expectedBodyNoAcl('custom_field:test'),
            ],
            'Query custom field (using dash)' => [
                'custom_field:test',
                self::expectedBodyNoAcl('custom_field:test'),
            ],
            'Query custom field (using space)' => [
                'custom field:test',
                self::expectedBodyNoAcl('custom field:test'),
            ],
            'Query logic operator (Uppercase)' => [
                'label:test AND custom_field:test',
                self::expectedBodyNoAcl('label:test AND custom_field:test'),
            ],
            'Query logic operator (Lowercase)' => [
                'label:test and custom_field:test',
                self::expectedBodyNoAcl('label:test and custom_field:test'),
            ],
            'Query logic operator (Mixed)' => [
                'label:test aNd custom_field:test',
                self::expectedBodyNoAcl('label:test aNd custom_field:test'),
            ],
            'Query using OR logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_OR custom_field:test1 ',
                self::expectedBodyNoAcl('label:test AND custom_field:test LOGIC_OR custom_field:test1 '),
            ],
            'Query using AND logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_AND custom_field:test1 ',
                self::expectedBodyNoAcl('label:test AND custom_field:test LOGIC_AND custom_field:test1 '),
            ],
            'Query using NOT logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_NOT custom_field:test1 ',
                self::expectedBodyNoAcl('label:test AND custom_field:test LOGIC_NOT custom_field:test1 '),
            ],
            'Query URIs' => [
                'https://test-act.docker.localhost/ontologies/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a',
                trim(<<<'JSON'
{"query":{"bool":{"must":[{"bool":{"should":[{"query_string":{"default_operator":"AND","query":"(\"https:\/\/test-act.docker.localhost\/ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a\")"}},{"nested":{"path":"attributes","query":{"bool":{"should":[{"term":{"attributes.value.raw":"https:\/\/test-act.docker.localhost\/ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a"}},{"match":{"attributes.raw_value":{"query":"https:\/\/test-act.docker.localhost\/ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a","operator":"and"}}},{"term":{"attributes.raw_value.raw":"https:\/\/test-act.docker.localhost\/ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a"}}],"minimum_should_match":1}}}}],"minimum_should_match":1}}]}},"sort":{"_id":{"order":"DESC","missing":"_last","unmapped_type":"long"},"label.raw":{"order":"DESC","missing":"_last","unmapped_type":"long"}}}
JSON
                ),
            ],
            'Query Field with URI' => [
                'delivery: https://test-act.docker.localhost/ontologies/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a',
                '{"query":{"query_string":{"default_operator":"AND","query":' .
                '"(delivery:\"https:\/\/test-act.docker.localhost' .
                '\/ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a\")"}},' .
                '"sort":{"_id":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"long"},"label.raw":{"order":"DESC","missing":"_last","unmapped_type":"long"}}}'
            ],
        ];
        // phpcs:enable Generic.Files.LineLength.TooLong
    }

    public function testGetSearchParamsResultsOnlyParentClassesUsesMatchAll(): void
    {
        $this->createAccessControlMock(false);

        $params = $this->subject->getSearchParams(
            'parent_classes:http://www.tao.lu/Ontologies/TAOResult.rdf#DeliveryResult',
            'results',
            0,
            10,
            '_id',
            'DESC'
        );

        $body = json_decode($params['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['match_all' => []], $body['query']);
    }

    private static function expectedBodies(): array
    {
        static $cache;

        if ($cache === null) {
            $path = dirname(__DIR__, 4) . '/resources/query-builder-expected-bodies.json';
            $cache = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        }

        return $cache;
    }

    private static function expectedBodyAcl(string $query): string
    {
        return self::expectedBodies()['acl'][$query];
    }

    private static function expectedBodyNoAcl(string $query): string
    {
        return self::expectedBodies()['noacl'][$query];
    }

    private function createAccessControlMock(bool $includeAccessControl): void
    {
        $this->accessControlEnabled = $includeAccessControl;

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
                    'http://www.tao.lu/Ontologies/TAOItem.rdf#ItemsManagerRole'
                ]
            );
    }
}
