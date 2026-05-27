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
 * Foundation, Inc., 31 Milk St # 960789 Boston, MA 02196 USA
 *
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\SearchEngine\Service;

use oat\taoAdvancedSearch\model\SearchEngine\QueryBlock;

/**
 * Elasticsearch clauses for nested {@code attributes} and temporary flat custom-metadata compatibility.
 *
 * When all documents are reindexed, remove {@see buildUnreindexedFlatCustomMetadataClause()} and its
 * use from {@see buildCustomFieldSearchQuery()}.
 */
class NestedAttributesQueryService
{
    private const ATTRIBUTES_FIELD = 'attributes';
    private const ATTRIBUTES_KEY_FIELD = 'attributes.key';
    private const ATTRIBUTES_VALUE_FIELD = 'attributes.value.raw';
    private const ATTRIBUTES_VALUE_TEXT_FIELD = 'attributes.value';
    private const ATTRIBUTES_RAW_VALUE_TEXT_FIELD = 'attributes.raw_value';
    private const ATTRIBUTES_RAW_VALUE_FIELD = 'attributes.raw_value.raw';

    /**
     * Custom property search: nested {@code attributes} plus unreindexed flat {@code HTMLArea_*} /
     * {@code TextBox_*} fields.
     */
    public function buildCustomFieldSearchQuery(QueryBlock $queryBlock, string $flatCustomMetadataQueryString): array
    {
        return [
            'bool' => [
                'should' => [
                    $this->buildUnreindexedFlatCustomMetadataClause($flatCustomMetadataQueryString),
                    $this->buildNestedAttributeCondition($queryBlock),
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    /**
     * Fieldless search: legacy root {@code query_string} (all top-level fields, including unreindexed flat custom
     * metadata) plus nested {@code attributes} for reindexed documents.
     */
    public function buildFieldlessSearchQuery(string $term, string $fieldlessRootQueryString): array
    {
        return [
            'bool' => [
                'should' => [
                    $this->buildUnreindexedFlatCustomMetadataClause($fieldlessRootQueryString),
                    $this->buildNestedFieldlessAttributesClause($term),
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    /**
     * @deprecated Remove when no documents store custom metadata as flat dynamic fields.
     */
    private function buildUnreindexedFlatCustomMetadataClause(string $flatCustomMetadataQueryString): array
    {
        return [
            'query_string' => [
                'default_operator' => 'AND',
                'query' => $flatCustomMetadataQueryString,
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
