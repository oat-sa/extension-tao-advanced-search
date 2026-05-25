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
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine\Normalizer;

use oat\tao\model\search\ResultSet;
use oat\taoAdvancedSearch\model\SearchEngine\Normalizer\SearchResultNormalizer;
use oat\taoAdvancedSearch\model\SearchEngine\SearchResult;
use PHPUnit\Framework\TestCase;

class SearchResultNormalizerTest extends TestCase
{
    /** @var SearchResultNormalizer */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new SearchResultNormalizer();
    }

    public function testNormalizeByByResultSet(): void
    {
        $resultSet = new ResultSet(
            [
                [
                    'label' => 'label',
                    'field' => 'value',
                    'field_raw' => 'value_raw',
                    'read_access' => 'read_access',
                    'TextBox_http_2_www_0_w3_0_org_1_2000_1_01_1_rdf-schema_3_label' => 'label',
                    'RadioBox_http_2_www_0_tao_0_lu_1_Ontologies_1_TAOItem_0_rdf_3_ItemModel' => 'model',
                    'type' => 'type',
                    'parent_classes' => 'parent_classes',
                    'class' => 'class',
                    'content' => 'content',
                    'model' => 'model'
                ],
            ],
            10
        );

        $this->assertEquals(
            new SearchResult(
                [
                    [
                        'label' => 'label',
                        'field' => 'value_raw',
                    ],
                ],
                10
            ),
            $this->subject->normalizeByByResultSet($resultSet)
        );
    }

    public function testNormalizeByByResultSetExpandsNestedAttributesWithRawValue(): void
    {
        $resultSet = new ResultSet(
            [
                [
                    'label' => 'Item A',
                    'attributes' => [
                        [
                            'key' => 'my_prop_key',
                            'type' => 'TextBox',
                            'value' => ['stored_value'],
                            'raw_value' => 'Human readable',
                        ],
                    ],
                ],
            ],
            1
        );

        $this->assertEquals(
            new SearchResult(
                [
                    [
                        'label' => 'Item A',
                        'TextBox_my_prop_key' => 'Human readable',
                    ],
                ],
                1
            ),
            $this->subject->normalizeByByResultSet($resultSet)
        );
    }

    public function testNormalizeByByResultSetUsesStoredValueWhenRawMissing(): void
    {
        $resultSet = new ResultSet(
            [
                [
                    'label' => 'Item A',
                    'attributes' => [
                        [
                            'key' => 'my_prop_key',
                            'type' => 'TextBox',
                            'value' => ['stored_value'],
                        ],
                    ],
                ],
            ],
            1
        );

        $this->assertEquals(
            new SearchResult(
                [
                    [
                        'label' => 'Item A',
                        'TextBox_my_prop_key' => ['stored_value'],
                    ],
                ],
                1
            ),
            $this->subject->normalizeByByResultSet($resultSet)
        );
    }

    public function testNormalizeByByResultSetWithEmptyAttributesReturnsLabelOnly(): void
    {
        $resultSet = new ResultSet(
            [
                [
                    'label' => 'Item B',
                    'attributes' => [],
                ],
            ],
            1
        );

        $this->assertEquals(
            new SearchResult(
                [
                    [
                        'label' => 'Item B',
                    ],
                ],
                1
            ),
            $this->subject->normalizeByByResultSet($resultSet)
        );
    }
}
