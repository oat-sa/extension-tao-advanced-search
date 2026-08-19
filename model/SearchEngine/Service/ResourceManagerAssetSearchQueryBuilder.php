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
            $mustClauses[] = ['terms' => ['mime_type' => $mimeTypes]];
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
        return [
            'bool' => [
                'should' => [
                    ['prefix' => ['label.raw' => $token]],
                    ['prefix' => ['location.raw' => $token]],
                    [
                        'nested' => [
                            'path' => 'attributes',
                            'query' => [
                                'prefix' => ['attributes.raw_value.raw' => $token],
                            ],
                        ],
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    private function buildMetadataClause(string $propertyUri, string $value): array
    {
        $queryBlock = new QueryBlock($propertyUri, $value);

        return $this->nestedAttributesQueryService->buildCustomFieldSearchQuery(
            $queryBlock,
            $this->resourceQueryBlockSupport->buildFlatCustomMetadataQueryString($queryBlock)
        );
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
