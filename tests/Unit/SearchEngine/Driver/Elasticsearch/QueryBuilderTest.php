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
use oat\generis\test\MockObject;
use oat\oatbox\log\LoggerService;
use oat\oatbox\session\SessionService;
use oat\oatbox\user\User;
use oat\tao\model\featureFlag\FeatureFlagCheckerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\QueryBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use oat\taoAdvancedSearch\model\SearchEngine\Service\LegacyResourceQueryConditionsBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesFeature;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesIndexResolver;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesQueryService;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceQueryBlockSupport;
use oat\taoAdvancedSearch\model\SearchEngine\Service\StructuredResourceSearchQueryBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Specification\UseAclSpecification;
use oat\taoResultServer\models\classes\ResultService;
use PHPUnit\Framework\TestCase;

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

    protected function setUp(): void
    {
        $this->sessionServiceMock = $this->createMock(SessionService::class);
        $this->permissionMock = $this->createMock(PermissionMock::class);
        $this->loggerService = $this->createMock(LoggerService::class);
        $this->user = $this->createMock(User::class);
        $this->useAclSpecification = $this->createMock(UseAclSpecification::class);
        $this->prefixer = $this->createMock(IndexPrefixer::class);

        $this->subject = $this->createQueryBuilderWithNestedAttributesDisabled();

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
        return [
            'with user access control and role access control' => [
                'test',
                '{"query":{"query_string":{"default_operator":"AND","query":' .
                '"(\\"test\\") AND (read_access:(\\"https:\\/\\/tao.docker.localhost\\/' .
                'ontologies\\/tao.rdf#i5f64514f1c36110793759fc28c0105b\\" OR' .
                ' \\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#BackOfficeRole\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#ItemsManagerRole\\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"}}}'
            ],
            'Simple query' => [
                'test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(\\"test\\") AND ' .
                '(read_access:(\\"https:\\/\\/tao.docker.localhost\\/' .
                'ontologies\\/tao.rdf#i5f64514f1c36110793759fc28c0105b\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#BackOfficeRole\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#ItemsManagerRole\\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"}}}'
            ],
            'Query specific field' => [
                'label:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\\"test\\") ' .
                'AND (read_access:(\\"https:\\/\\/tao.docker.localhost\\/' .
                'ontologies\\/tao.rdf#i5f64514f1c36110793759fc28c0105b\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#BackOfficeRole\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#ItemsManagerRole\\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"}}}'
            ],
            'Query specific field (variating case)' => [
                'LaBeL:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\\"test\\") ' .
                'AND (read_access:(\\"https:\\/\\/tao.docker.localhost\\/' .
                'ontologies\\/tao.rdf#i5f64514f1c36110793759fc28c0105b\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#BackOfficeRole\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#ItemsManagerRole\\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"}}}'
            ],
            'Query custom field (using underscore)' => [
                'custom_field:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(HTMLArea_custom_field:' .
                '\\"test\\" OR TextArea_custom_field:\\"test\\" OR ' .
                'TextBox_custom_field:\\"test\\" OR ComboBox_custom_field:\\"test\\" ' .
                'OR CheckBox_custom_field:\\"test\\" OR RadioBox_custom_field:\\"test\\" ' .
                'OR SearchTextBox_custom_field:\\"test\\" OR SearchDropdown_custom_field:\\"test\\" ' .
                'OR Readonly_custom_field:\\"test\\") AND (read_access:(\\"https:\\/\\/tao.docker.localhost\\/' .
                'ontologies\\/tao.rdf#i5f64514f1c36110793759fc28c0105b\\" OR \\"http:\\/\\/www.tao.lu\\/Ontologies\\/' .
                'TAOItem.rdf#BackOfficeRole\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#ItemsManagerRole\\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"}}}'
            ],
            'Query custom field (using dash)' => [
                'custom_field:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"' .
                '(HTMLArea_custom_field:\\"test\\" OR TextArea_custom_field:\\"test\\" OR ' .
                'TextBox_custom_field:\\"test\\" OR ComboBox_custom_field:\\"test\\"' .
                ' OR CheckBox_custom_field:\\"test\\" OR RadioBox_custom_field:\\"test\\" ' .
                'OR SearchTextBox_custom_field:\\"test\\" OR SearchDropdown_custom_field:\\"test\\" ' .
                'OR Readonly_custom_field:\\"test\\") AND (read_access:(\\"https:\\/\\/tao.docker.localhost\\/' .
                'ontologies\\/tao.rdf#i5f64514f1c36110793759fc28c0105b\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#BackOfficeRole\\" OR ' .
                '\\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#ItemsManagerRole\\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"}}}'
            ],
            'Query custom field (using space)' => [
                'custom field:test',
                'body' => '{"query":{"query_string":{"default_operator":"AND",' .
                    '"query":"(HTMLArea_custom field:\"test\" ' .
                    'OR TextArea_custom field:\"test\" OR TextBox_custom ' .
                    'field:\"test\" OR ComboBox_custom field:\"test\" ' .
                    'OR CheckBox_custom field:\"test\" OR RadioBox_custom ' .
                    'field:\"test\" OR SearchTextBox_custom field:\"test\" ' .
                    'OR SearchDropdown_custom field:\"test\" OR Readonly_custom field:\\"test\\")' .
                    ' AND (read_access:(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf' .
                    '#i5f64514f1c36110793759fc28c0105b\" OR ' .
                    '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" ' .
                    'OR \"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#ItemsManagerRole\"))"}},' .
                    '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                    '"unmapped_type":"keyword"},"label.raw":{"order":"DESC",' .
                    '"missing":"_last","unmapped_type":"keyword"}}}',
            ],
            'Query logic operator (Uppercase)' => [
                'label:test AND custom_field:test',
                '{"query":{"query_string":{"default_operator":"AND","query":' .
                '"(label:\\"test\\") AND (HTMLArea_custom_field:\\"test\\" OR TextArea_custom_' .
                'field:\\"test\\" OR TextBox_custom_field:\\"test\\" OR ' .
                'ComboBox_custom_field:\\"test\\" OR CheckBox_custom_field:\\"test\\" OR RadioBox_' .
                'custom_field:\\"test\\" OR SearchTextBox_custom_field:\\"test\\" ' .
                'OR SearchDropdown_custom_field:\\"test\\" OR Readonly_custom_field:\\"test\\")' .
                ' AND (read_access:(\\"https:\\/' .
                '\\/tao.docker.localhost\\/ontologies\\/tao.rdf#i5f64514f1c36110793759fc28c0105b\\"' .
                ' OR \\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#' .
                'BackOfficeRole\\" OR \\"http:\\/\\/www.tao.lu\\/Ontologies\\/TAOItem.rdf#ItemsManagerRole\\"))"}}' .
                ',"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"keyword"}}}'
            ],
            'Query logic operator (Lowercase)' => [
                'label:test and custom_field:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\"test\") AND ' .
                '(HTMLArea_custom_field:\"test\" OR TextArea_custom_field:\"test\" OR TextBox_custom_field:\"test\" ' .
                'OR ComboBox_custom_field:\"test\" OR CheckBox_custom_field:\"test\" ' .
                'OR RadioBox_custom_field:\"test\" ' .
                'OR SearchTextBox_custom_field:\"test\" OR SearchDropdown_custom_field:\"test\" ' .
                'OR Readonly_custom_field:\"test\") AND (read_access:' .
                '(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#ItemsManagerRole\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"keyword"}}}'
            ],
            'Query logic operator (Mixed)' => [
                'label:test aNd custom_field:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\"test\") AND ' .
                '(HTMLArea_custom_field:\"test\" OR TextArea_custom_field:\"test\" OR TextBox_custom_field:\"test\" ' .
                'OR ComboBox_custom_field:\"test\" OR CheckBox_custom_field:\"test\"' .
                ' OR RadioBox_custom_field:\"test\" ' .
                'OR SearchTextBox_custom_field:\"test\" OR SearchDropdown_custom_field:\"test\" ' .
                'OR Readonly_custom_field:\"test\") AND (read_access:' .
                '(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#ItemsManagerRole\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"keyword"}}}'
            ],
            'Query using OR logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_OR custom_field:test1 ',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\"test\") AND ' .
                '((HTMLArea_custom_field:\"test\" OR TextArea_custom_field:\"test\" OR TextBox_custom_field:\"test\" ' .
                'OR ComboBox_custom_field:\"test\" OR CheckBox_custom_field:\"test\" ' .
                'OR RadioBox_custom_field:\"test\" ' .
                'OR SearchTextBox_custom_field:\"test\" OR SearchDropdown_custom_field:\"test\" ' .
                'OR Readonly_custom_field:\"test\") ' .
                'OR (HTMLArea_custom_field:\"test1\" OR TextArea_custom_field:\"test1\" ' .
                'OR TextBox_custom_field:\"test1\" ' .
                'OR ComboBox_custom_field:\"test1\" OR CheckBox_custom_field:\"test1\"' .
                ' OR RadioBox_custom_field:\"test1\" ' .
                'OR SearchTextBox_custom_field:\"test1\" OR SearchDropdown_custom_field:\"test1\" ' .
                'OR Readonly_custom_field:\"test1\")) AND (read_access:' .
                '(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#ItemsManagerRole\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":' .
                '{"order":"DESC","missing":"_last","unmapped_type":"keyword"}}}',
            ],
            'Query using AND logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_AND custom_field:test1 ',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\"test\") AND ' .
                '((HTMLArea_custom_field:\"test\" OR TextArea_custom_field:\"test\" OR TextBox_custom_field:\"test\" ' .
                'OR ComboBox_custom_field:\"test\" OR CheckBox_custom_field:\"test\" ' .
                'OR RadioBox_custom_field:\"test\" ' .
                'OR SearchTextBox_custom_field:\"test\" OR SearchDropdown_custom_field:\"test\" ' .
                'OR Readonly_custom_field:\"test\") ' .
                'AND (HTMLArea_custom_field:\"test1\" OR TextArea_custom_field:\"test1\" ' .
                'OR TextBox_custom_field:\"test1\" ' .
                'OR ComboBox_custom_field:\"test1\" OR CheckBox_custom_field:\"test1\"' .
                ' OR RadioBox_custom_field:\"test1\" ' .
                'OR SearchTextBox_custom_field:\"test1\" OR SearchDropdown_custom_field:\"test1\" ' .
                'OR Readonly_custom_field:\"test1\")) AND (read_access:' .
                '(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#ItemsManagerRole\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":' .
                '{"order":"DESC","missing":"_last","unmapped_type":"keyword"}}}',
            ],
            'Query using NOT logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_NOT custom_field:test1 ',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\"test\") AND ' .
                'NOT ((HTMLArea_custom_field:\"test\" OR TextArea_custom_field:\"test\" ' .
                'OR TextBox_custom_field:\"test\" ' .
                'OR ComboBox_custom_field:\"test\" OR CheckBox_custom_field:\"test\" ' .
                'OR RadioBox_custom_field:\"test\" ' .
                'OR SearchTextBox_custom_field:\"test\" ' .
                'OR SearchDropdown_custom_field:\"test\" OR Readonly_custom_field:\"test\") ' .
                'OR (HTMLArea_custom_field:\"test1\" OR TextArea_custom_field:\"test1\" ' .
                'OR TextBox_custom_field:\"test1\" ' .
                'OR ComboBox_custom_field:\"test1\" OR CheckBox_custom_field:\"test1\"' .
                ' OR RadioBox_custom_field:\"test1\" ' .
                'OR SearchTextBox_custom_field:\"test1\" OR SearchDropdown_custom_field:\"test1\" ' .
                'OR Readonly_custom_field:\"test1\")) AND (read_access:' .
                '(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#ItemsManagerRole\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":' .
                '{"order":"DESC","missing":"_last","unmapped_type":"keyword"}}}',
            ],
            'Query URIs' => [
                'https://test-act.docker.localhost/ontologies/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a',
                '{"query":{"query_string":{"default_operator":"AND","query":"(\"https:\/\/test-act.docker.localhost\/' .
                'ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a\") AND (read_access:' .
                '(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#' .
                'ItemsManagerRole\"))"}},"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"keyword"}}}'
            ],
            'Query Field with URI' => [
                'delivery: https://test-act.docker.localhost/ontologies/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a',
                '{"query":{"query_string":{"default_operator":"AND","query"' .
                ':"(delivery:\"https:\/\/test-act.docker.localhost\/' .
                'ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a\") AND (read_access:' .
                '(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#' .
                'ItemsManagerRole\"))"}},"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"keyword"}}}'
            ],
            'Query term with a backslash' => [
                'some\ term',
                '{"query":{"query_string":{"default_operator":"AND","query":' .
                '"(\"some\\\\\\\\ term\") AND (read_access:' .
                '(\"https:\/\/tao.docker.localhost\/ontologies\/tao.rdf#i5f64514f1c36110793759fc28c0105b\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#BackOfficeRole\" OR ' .
                '\"http:\/\/www.tao.lu\/Ontologies\/TAOItem.rdf#ItemsManagerRole\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"keyword"}}}'
            ],
        ];
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
        return [
            'Simple query' => [
                'test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(\"test\")"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"keyword"}}}'
            ],
            'Query specific field' => [
                'label:test',
                '{"query":{"query_string":{"default_operator":"AND","query":' .
                '"(label:\"test\")"}},"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"keyword"}}}'
            ],
            'Query specific field (variating case)' => [
                'LaBeL:test',
                '{"query":{"query_string":{"default_operator":"AND","query"' .
                ':"(label:\"test\")"}},"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC",' .
                '"missing":"_last","unmapped_type":"keyword"}}}'
            ],
            'Query custom field (using underscore)' => [
                'custom_field:test',
                '{"query":{"query_string":{"default_operator":"AND",' .
                '"query":"(HTMLArea_custom_field:\"test\" OR ' .
                'TextArea_custom_field:\"test\" OR TextBox_custom_field' .
                ':\"test\" OR ComboBox_custom_field:\"test\" ' .
                'OR CheckBox_custom_field:\"test\" OR RadioBox_custom_field:' .
                '\"test\" OR SearchTextBox_custom_field:\"test\" ' .
                'OR SearchDropdown_custom_field:\"test\" OR Readonly_custom_field:\"test\")"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"}}}'
            ],
            'Query custom field (using dash)' => [
                'custom_field:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(HTMLArea_custom_field:\"test\" OR ' .
                'TextArea_custom_field:\"test\" OR TextBox_custom_field:\"test\" OR ComboBox_custom_field:\"test\" ' .
                'OR CheckBox_custom_field:\"test\" OR RadioBox_custom_field:' .
                '\"test\" OR SearchTextBox_custom_field:\"test\" OR SearchDropdown_custom_field:\"test\" ' .
                'OR Readonly_custom_field:\"test\")"}},"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"}}}'
            ],
            'Query custom field (using space)' => [
                'custom field:test',
                'body' => '{"query":{"query_string":{"default_operator":"AND","query":"(HTMLArea_custom ' .
                    'field:\"test\" OR TextArea_custom field:\"test\" OR TextBox_custom field:\"test\" ' .
                    'OR ComboBox_custom field:\"test\" OR CheckBox_custom field:\"test\" OR RadioBox_custom ' .
                    'field:\"test\" OR SearchTextBox_custom field:\"test\" OR SearchDropdown_custom field:' .
                    '\"test\" OR Readonly_custom field:\"test\")"}},' .
                    '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                    '"unmapped_type":"keyword"},"label.raw":{"order":"DESC","missing":"_last",' .
                    '"unmapped_type":"keyword"}}}',
            ],
            'Query logic operator (Uppercase)' => [
                'label:test AND custom_field:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\"test\") ' .
                'AND (HTMLArea_custom_field:\"test\" OR TextArea_custom_field:\"test\" OR ' .
                'TextBox_custom_field:\"test\" OR ComboBox_custom_field:\"test\" OR CheckBox_custom_field:' .
                '\"test\" OR RadioBox_custom_field:\"test\" OR SearchTextBox_custom_field:\"test\" OR ' .
                'SearchDropdown_custom_field:\"test\" OR Readonly_custom_field:\"test\")"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":' .
                '"DESC","missing":"_last","unmapped_type":"keyword"}}}',
            ],
            'Query logic operator (Lowercase)' => [
                'label:test and custom_field:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\"test\") AND ' .
                '(HTMLArea_custom_field:\"test\" OR TextArea_custom_field:\"test\" OR TextBox_custom_field:' .
                '\"test\" OR ComboBox_custom_field:\"test\" OR CheckBox_custom_field:\"test\" OR ' .
                'RadioBox_custom_field:\"test\" OR SearchTextBox_custom_field:\"test\" OR ' .
                'SearchDropdown_custom_field:\"test\" OR Readonly_custom_field:\"test\")"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":' .
                '"DESC","missing":"_last","unmapped_type":"keyword"}}}',
            ],
            'Query logic operator (Mixed)' => [
                'label:test aNd custom_field:test',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\"test\") AND ' .
                '(HTMLArea_custom_field:\"test\" OR TextArea_custom_field:\"test\" OR TextBox_custom_field:\"test\" ' .
                'OR ComboBox_custom_field:\"test\" OR CheckBox_custom_field:\"test\"' .
                ' OR RadioBox_custom_field:\"test\" ' .
                'OR SearchTextBox_custom_field:\"test\" OR SearchDropdown_custom_field:\"test\" ' .
                'OR Readonly_custom_field:\"test\")"}},"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":' .
                '{"order":"DESC","missing":"_last","unmapped_type":"keyword"}}}',
            ],
            'Query using OR logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_OR custom_field:test1 ',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\"test\") AND ' .
                '((HTMLArea_custom_field:\"test\" OR TextArea_custom_field:\"test\" OR TextBox_custom_field:\"test\" ' .
                'OR ComboBox_custom_field:\"test\" OR CheckBox_custom_field:\"test\" ' .
                'OR RadioBox_custom_field:\"test\" ' .
                'OR SearchTextBox_custom_field:\"test\" OR SearchDropdown_custom_field:\"test\" ' .
                'OR Readonly_custom_field:\"test\") OR (HTMLArea_custom_field:\"test1\" ' .
                'OR TextArea_custom_field:\"test1\" OR TextBox_custom_field:\"test1\" ' .
                'OR ComboBox_custom_field:\"test1\" OR CheckBox_custom_field:\"test1\"' .
                ' OR RadioBox_custom_field:\"test1\" OR SearchTextBox_custom_field:\"test1\" ' .
                'OR SearchDropdown_custom_field:\"test1\" OR Readonly_custom_field:\"test1\"))"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last","unmapped_type":"keyword"},"label.raw":' .
                '{"order":"DESC","missing":"_last","unmapped_type":"keyword"}}}',
            ],
            'Query using AND logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_AND custom_field:test1 ',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\"test\") AND ' .
                '((HTMLArea_custom_field:\"test\" OR TextArea_custom_field:\"test\" OR TextBox_custom_field:\"test\" ' .
                'OR ComboBox_custom_field:\"test\" OR CheckBox_custom_field:\"test\" ' .
                'OR RadioBox_custom_field:\"test\" ' .
                'OR SearchTextBox_custom_field:\"test\" OR SearchDropdown_custom_field:\"test\" ' .
                'OR Readonly_custom_field:\"test\") ' .
                'AND (HTMLArea_custom_field:\"test1\" OR TextArea_custom_field:\"test1\" ' .
                'OR TextBox_custom_field:\"test1\" ' .
                'OR ComboBox_custom_field:\"test1\" OR CheckBox_custom_field:\"test1\"' .
                ' OR RadioBox_custom_field:\"test1\" ' .
                'OR SearchTextBox_custom_field:\"test1\" OR SearchDropdown_custom_field:\"test1\" ' .
                'OR Readonly_custom_field:\"test1\"))"}},"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":' .
                '{"order":"DESC","missing":"_last","unmapped_type":"keyword"}}}',
            ],
            'Query using NOT logic operator to join list field values' => [
                'label:test AND custom_field:test LOGIC_NOT custom_field:test1 ',
                '{"query":{"query_string":{"default_operator":"AND","query":"(label:\"test\") AND ' .
                'NOT ((HTMLArea_custom_field:\"test\" OR TextArea_custom_field:\"test\" ' .
                'OR TextBox_custom_field:\"test\" ' .
                'OR ComboBox_custom_field:\"test\" OR CheckBox_custom_field:\"test\" ' .
                'OR RadioBox_custom_field:\"test\" ' .
                'OR SearchTextBox_custom_field:\"test\" OR SearchDropdown_custom_field:\"test\" ' .
                'OR Readonly_custom_field:\"test\") OR (HTMLArea_custom_field:\"test1\" ' .
                'OR TextArea_custom_field:\"test1\" OR TextBox_custom_field:\"test1\" ' .
                'OR ComboBox_custom_field:\"test1\" OR CheckBox_custom_field:\"test1\"' .
                ' OR RadioBox_custom_field:\"test1\" ' .
                'OR SearchTextBox_custom_field:\"test1\" OR SearchDropdown_custom_field:\"test1\" ' .
                'OR Readonly_custom_field:\"test1\"))"}},"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":' .
                '{"order":"DESC","missing":"_last","unmapped_type":"keyword"}}}',
            ],
            'Query URIs' => [
                'https://test-act.docker.localhost/ontologies/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a',
                '{"query":{"query_string":{"default_operator":"AND","query":"(\"https:\/\/test-act.docker.localhost' .
                '\/ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a\")"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"}}}'
            ],
            'Query Field with URI' => [
                'delivery: https://test-act.docker.localhost/ontologies/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a',
                '{"query":{"query_string":{"default_operator":"AND","query":' .
                '"(delivery:\"https:\/\/test-act.docker.localhost' .
                '\/ontologies\/tao.rdf#i5f200ed20e80a8c259ebe410db7f6a\")"}},' .
                '"sort":{"updated_at.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"},"label.raw":{"order":"DESC","missing":"_last",' .
                '"unmapped_type":"keyword"}}}'
            ],
        ];
    }

    /**
     * Legacy flat query_string path (master behaviour). FEATURE_FLAG_ADVANCED_SEARCH_DISABLE_NESTED_ATTRIBUTES enabled.
     */
    private function createQueryBuilderWithNestedAttributesDisabled(): QueryBuilder
    {
        $featureFlagChecker = $this->createMock(FeatureFlagCheckerInterface::class);
        $featureFlagChecker
            ->method('isEnabled')
            ->with(NestedAttributesFeature::FEATURE_FLAG_DISABLE_NESTED_ATTRIBUTES)
            ->willReturn(true);

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

    public function testSortRemapsSystemIdByIndex(): void
    {
        $this->createAccessControlMock(false);

        foreach (['_id', 'id'] as $order) {
            $params = $this->subject->getSearchParams('label:test', 'items', 0, 10, $order, 'DESC');
            $body = json_decode($params['body'], true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(['updated_at.raw', 'label.raw'], array_keys($body['sort']));
            $this->assertArrayNotHasKey('_id', $body['sort']);
            $this->assertSame('keyword', $body['sort']['updated_at.raw']['unmapped_type']);
            $this->assertSame('keyword', $body['sort']['label.raw']['unmapped_type']);
        }

        foreach (['_id', 'id'] as $order) {
            $params = $this->subject->getSearchParams('label:test', 'results', 0, 10, $order, 'DESC');
            $body = json_decode($params['body'], true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(
                ['delivery_execution_start_time.raw', 'label.raw'],
                array_keys($body['sort'])
            );
            $this->assertArrayNotHasKey('_id', $body['sort']);
            $this->assertSame(
                'keyword',
                $body['sort']['delivery_execution_start_time.raw']['unmapped_type']
            );
        }

        $params = $this->subject->getSearchParams('label:test', 'items', 0, 10, 'delivery.raw', 'DESC');
        $body = json_decode($params['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['delivery.raw', 'label.raw'], array_keys($body['sort']));
        $this->assertArrayNotHasKey('_id', $body['sort']);
        $this->assertSame('keyword', $body['sort']['delivery.raw']['unmapped_type']);
        $this->assertSame('keyword', $body['sort']['label.raw']['unmapped_type']);
    }

    public function testCanonicalDeliveryResultClassUriResolvesIndexAndIdSort(): void
    {
        $this->createAccessControlMock(false);

        $this->prefixer = $this->createMock(IndexPrefixer::class);
        $this->prefixer
            ->expects($this->atLeastOnce())
            ->method('prefix')
            ->with(IndexerInterface::DELIVERY_RESULTS_INDEX)
            ->willReturn('tenant_delivery-results');
        $this->subject = $this->createQueryBuilderWithNestedAttributesDisabled();

        $params = $this->subject->getSearchParams(
            'label:test',
            ResultService::DELIVERY_RESULT_CLASS_URI,
            0,
            10,
            '_id',
            'DESC'
        );

        $this->assertSame('tenant_delivery-results', $params['index']);
        $this->assertNotSame(IndexerInterface::UNCLASSIFIEDS_DOCUMENTS_INDEX, $params['index']);

        $body = json_decode($params['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(
            ['delivery_execution_start_time.raw', 'label.raw'],
            array_keys($body['sort'])
        );
        $this->assertArrayNotHasKey('updated_at.raw', $body['sort']);
        $this->assertArrayNotHasKey('_id', $body['sort']);
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
                    'http://www.tao.lu/Ontologies/TAOItem.rdf#ItemsManagerRole'
                ]
            );
    }
}
