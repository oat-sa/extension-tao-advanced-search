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
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\SearchEngine\Service;

use oat\taoAdvancedSearch\model\SearchEngine\QueryBlock;

/**
 * Builds Elasticsearch nested {@code attributes} query clauses and legacy/nested compatibility wrappers.
 */
class NestedAttributesQueryService
{
    private const ATTRIBUTES_FIELD = 'attributes';
    private const ATTRIBUTES_KEY_FIELD = 'attributes.key';
    private const ATTRIBUTES_VALUE_FIELD = 'attributes.value.raw';
    private const ATTRIBUTES_VALUE_TEXT_FIELD = 'attributes.value';
    private const ATTRIBUTES_RAW_VALUE_TEXT_FIELD = 'attributes.raw_value';
    private const ATTRIBUTES_RAW_VALUE_FIELD = 'attributes.raw_value.raw';

    private NestedAttributesFeature $nestedAttributesFeature;

    public function __construct(NestedAttributesFeature $nestedAttributesFeature)
    {
        $this->nestedAttributesFeature = $nestedAttributesFeature;
    }

    public function isNestedQueryEnabledForIndex(string $index): bool
    {
        return $this->nestedAttributesFeature->shouldUseNestedAttributes($index);
    }

    public function buildCustomCompatibilityCondition(
        QueryBlock $queryBlock,
        string $index,
        string $legacyQueryString
    ): array {
        if (!$this->isNestedQueryEnabledForIndex($index)) {
            return [
                'query_string' => [
                    'default_operator' => 'AND',
                    'query' => $legacyQueryString,
                ],
            ];
        }

        return [
            'bool' => [
                'should' => [
                    [
                        'query_string' => [
                            'default_operator' => 'AND',
                            'query' => $legacyQueryString,
                        ],
                    ],
                    $this->buildNestedAttributeCondition($queryBlock),
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    public function buildFieldlessRootAndNestedAttributesCondition(string $term): array
    {
        return [
            'bool' => [
                'should' => [
                    [
                        'query_string' => [
                            'default_operator' => 'AND',
                            'query' => sprintf('("%s")', $term),
                        ],
                    ],
                    $this->buildNestedFieldlessAttributesClause($term),
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    private function buildNestedAttributeCondition(QueryBlock $queryBlock): array
    {
        return [
            'nested' => [
                'path' => self::ATTRIBUTES_FIELD,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => [self::ATTRIBUTES_KEY_FIELD => $queryBlock->getField()]],
                            $this->buildNestedAttributeValueClause($queryBlock->getTerm()),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildNestedAttributeValueClause(string $term): array
    {
        return [
            'bool' => [
                'should' => [
                    ['term' => [self::ATTRIBUTES_VALUE_FIELD => $term]],
                    [
                        'match' => [
                            self::ATTRIBUTES_VALUE_TEXT_FIELD => [
                                'query' => $term,
                                'operator' => 'and',
                            ],
                        ],
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    private function buildNestedFieldlessAttributesClause(string $term): array
    {
        return [
            'nested' => [
                'path' => self::ATTRIBUTES_FIELD,
                'query' => [
                    'bool' => [
                        'should' => [
                            ['term' => [self::ATTRIBUTES_VALUE_FIELD => $term]],
                            [
                                'match' => [
                                    self::ATTRIBUTES_RAW_VALUE_TEXT_FIELD => [
                                        'query' => $term,
                                        'operator' => 'and',
                                    ],
                                ],
                            ],
                            ['term' => [self::ATTRIBUTES_RAW_VALUE_FIELD => $term]],
                        ],
                        'minimum_should_match' => 1,
                    ],
                ],
            ],
        ];
    }
}
