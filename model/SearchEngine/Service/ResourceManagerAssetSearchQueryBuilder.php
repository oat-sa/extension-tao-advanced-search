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

namespace oat\taoAdvancedSearch\model\SearchEngine\Service;

use oat\taoAdvancedSearch\model\SearchEngine\QueryBlock;
use oat\taoItems\model\media\AssetSearchQuery;

/**
 * Builds raw Elasticsearch DSL for Resource Manager asset search.
 */
class ResourceManagerAssetSearchQueryBuilder
{
    public const PREFIX_MATCH_MIN_LENGTH = 3;

    private const SORT_FIELD_MAP = [
        AssetSearchQuery::SORT_LABEL => 'label.raw',
        AssetSearchQuery::SORT_LOCATION => 'location.raw',
        AssetSearchQuery::SORT_UPDATED_AT => 'updated_at.raw',
    ];

    /** @var NestedAttributesQueryService */
    private $nestedAttributesQueryService;

    /** @var ResourceQueryBlockSupport */
    private $resourceQueryBlockSupport;

    public function __construct(
        NestedAttributesQueryService $nestedAttributesQueryService,
        ResourceQueryBlockSupport $resourceQueryBlockSupport
    ) {
        $this->nestedAttributesQueryService = $nestedAttributesQueryService;
        $this->resourceQueryBlockSupport = $resourceQueryBlockSupport;
    }

    /**
     * @param array<string, string> $metadataCriteria
     */
    public function build(
        AssetSearchQuery $query,
        string $scopeLocation,
        array $metadataCriteria = []
    ): array {
        $mustClauses = [];

        if ($scopeLocation !== '') {
            $mustClauses[] = $this->buildScopeClause($scopeLocation);
        }

        $trimmedQuery = trim($query->getQuery());
        $queryTokens = $this->tokenize($trimmedQuery);
        if ($trimmedQuery !== '' && $queryTokens === []) {
            $mustClauses[] = ['match_none' => (object)[]];
        } else {
            foreach ($queryTokens as $token) {
                $mustClauses[] = $this->buildUniversalTokenClause($token);
            }
        }

        $mimeTypes = array_values(array_filter($query->getFilter(), static function ($value): bool {
            return is_string($value) && $value !== '';
        }));
        if ($mimeTypes !== []) {
            // Prefer keyword mapping from assets.conf.php. Some local/legacy indices
            // still map mime_type as text+keyword (dynamic), so also match .keyword.
            // Docs without mime_type are kept for PHP post-filter via ontology.
            $mustClauses[] = [
                'bool' => [
                    'should' => [
                        ['terms' => ['mime_type' => $mimeTypes]],
                        ['terms' => ['mime_type.keyword' => $mimeTypes]],
                        [
                            'bool' => [
                                'must_not' => [
                                    ['exists' => ['field' => 'mime_type']],
                                ],
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ];
        }

        foreach ($metadataCriteria as $propertyUri => $value) {
            if (!is_string($propertyUri) || !is_string($value) || $value === '') {
                continue;
            }

            $mustClauses[] = $this->buildMetadataClause($propertyUri, $value);
        }

        $body = [
            'query' => [
                'bool' => [
                    'must' => $mustClauses !== [] ? $mustClauses : [['match_all' => (object)[]]],
                ],
            ],
            'sort' => $this->buildSort($query),
        ];

        return $body;
    }

    private function buildScopeClause(string $scopeLocation): array
    {
        return [
            'bool' => [
                'should' => [
                    ['term' => ['location.raw' => $scopeLocation]],
                    ['prefix' => ['location.raw' => $scopeLocation . '/']],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    private function buildUniversalTokenClause(string $token): array
    {
        $pattern = $this->buildTrailingTokenRegexp($token);

        return [
            'bool' => [
                'should' => [
                    [
                        'regexp' => [
                            'label.raw' => [
                                'value' => $pattern,
                                'case_insensitive' => true,
                            ],
                        ],
                    ],
                    [
                        'regexp' => [
                            'location.raw' => [
                                'value' => $pattern,
                                'case_insensitive' => true,
                            ],
                        ],
                    ],
                    [
                        'nested' => [
                            'path' => 'attributes',
                            'query' => [
                                'regexp' => [
                                    'attributes.raw_value.raw' => [
                                        'value' => $pattern,
                                        'case_insensitive' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    /**
     * Trailing-token match aligned with AssetSearchBuilder::tokenize:
     * split on non-alphanumeric, then prefix (>=3 chars) or exact (<3) on a token.
     */
    private function buildTrailingTokenRegexp(string $token): string
    {
        $escaped = $this->escapeLuceneRegexp($token);
        $isPrefix = mb_strlen($token, 'UTF-8') >= self::PREFIX_MATCH_MIN_LENGTH;
        $tokenBody = $isPrefix ? $escaped . '[A-Za-z0-9]*' : $escaped;
        $afterToken = $isPrefix ? '.*' : '([^A-Za-z0-9].*)?';

        // Whole-string Lucene regexp: token at start OR after a non-alnum delimiter.
        return $tokenBody . $afterToken . '|.*[^A-Za-z0-9]' . $tokenBody . $afterToken;
    }

    private function escapeLuceneRegexp(string $value): string
    {
        return preg_replace('/([.\\+*?\\[\\]^$(){}=!<>|:-])/', '\\\\$1', $value) ?? $value;
    }

    private function buildMetadataClause(string $propertyUri, string $value): array
    {
        $queryBlock = new QueryBlock($propertyUri, $value);
        $legacyOrExact = $this->nestedAttributesQueryService->buildCustomFieldSearchQuery(
            $queryBlock,
            $this->resourceQueryBlockSupport->buildFlatCustomMetadataQueryString($queryBlock)
        );

        // Text criteria often need trailing-token match (e.g. label "47" → "mp3_47.mp3"),
        // while enum/URI values still match via exact term in $legacyOrExact.
        // AC4: keeps existing exact-keyword OR analyzed-text semantics and AND-combines
        // independently supplied criteria; trailing-token is an additive should clause.
        $pattern = $this->buildTrailingTokenRegexp($value);
        $trailingToken = [
            'nested' => [
                'path' => 'attributes',
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['attributes.key' => $propertyUri]],
                            [
                                'bool' => [
                                    'should' => [
                                        [
                                            'regexp' => [
                                                'attributes.value.raw' => [
                                                    'value' => $pattern,
                                                    'case_insensitive' => true,
                                                ],
                                            ],
                                        ],
                                        [
                                            'regexp' => [
                                                'attributes.raw_value.raw' => [
                                                    'value' => $pattern,
                                                    'case_insensitive' => true,
                                                ],
                                            ],
                                        ],
                                    ],
                                    'minimum_should_match' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return [
            'bool' => [
                'should' => [
                    $legacyOrExact,
                    $trailingToken,
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    /**
     * @return array<int, array<string, array<string, string>>>
     */
    private function buildSort(AssetSearchQuery $query): array
    {
        $field = self::SORT_FIELD_MAP[$query->getSortBy()] ?? self::SORT_FIELD_MAP[AssetSearchQuery::SORT_LABEL];

        return [
            [
                $field => [
                    'order' => $query->getSortDir(),
                ],
            ],
        ];
    }

    /**
     * @return string[]
     */
    private function tokenize(string $value): array
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: [];

        return array_values(array_filter($parts, static function (string $part): bool {
            return $part !== '';
        }));
    }
}
