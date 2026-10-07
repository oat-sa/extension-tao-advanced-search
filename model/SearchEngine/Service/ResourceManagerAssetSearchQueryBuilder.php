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

use oat\generis\model\data\permission\PermissionInterface;
use oat\oatbox\session\SessionService;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\QueryBlock;
use oat\taoAdvancedSearch\model\SearchEngine\Specification\UseAclSpecification;
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

    /** @var AssetSearchTokenizer */
    private $assetSearchTokenizer;

    /** @var SessionService */
    private $sessionService;

    /** @var PermissionInterface */
    private $permission;

    /** @var UseAclSpecification */
    private $useAclSpecification;

    /** Whether the last {@see build()} added a {@code read_access} filter. */
    private $lastBuildAppliedAccessControl = false;

    public function __construct(
        NestedAttributesQueryService $nestedAttributesQueryService,
        ResourceQueryBlockSupport $resourceQueryBlockSupport,
        SessionService $sessionService,
        PermissionInterface $permission,
        UseAclSpecification $useAclSpecification,
        AssetSearchTokenizer $assetSearchTokenizer = null
    ) {
        $this->nestedAttributesQueryService = $nestedAttributesQueryService;
        $this->resourceQueryBlockSupport = $resourceQueryBlockSupport;
        $this->sessionService = $sessionService;
        $this->permission = $permission;
        $this->useAclSpecification = $useAclSpecification;
        $this->assetSearchTokenizer = $assetSearchTokenizer ?? new AssetSearchTokenizer();
    }

    public function lastBuildAppliedAccessControl(): bool
    {
        return $this->lastBuildAppliedAccessControl;
    }

    /**
     * @param array<string, string> $metadataCriteria
     */
    public function build(
        AssetSearchQuery $query,
        string $scopeClassUri,
        array $metadataCriteria = []
    ): array {
        $this->lastBuildAppliedAccessControl = false;
        $mustClauses = [];

        if ($scopeClassUri !== '') {
            $mustClauses[] = $this->buildScopeClause($scopeClassUri);
        }

        if ($this->includeAccessControlInQuery()) {
            $mustClauses[] = $this->resourceQueryBlockSupport->buildAccessControlMustClause(
                $this->getAccessControlIdentifiers()
            );
            $this->lastBuildAppliedAccessControl = true;
        }

        $trimmedQuery = trim($query->getQuery());
        $queryTokens = $this->assetSearchTokenizer->tokenize($trimmedQuery);
        if ($trimmedQuery !== '' && $queryTokens === []) {
            $mustClauses[] = ['match_none' => (object)[]];
        } else {
            foreach ($queryTokens as $token) {
                $mustClauses[] = $this->buildDocumentSearchTokenClause($token);
            }
        }

        $mimeTypes = array_values(array_filter($query->getFilter(), static function ($value): bool {
            return is_string($value) && $value !== '';
        }));
        if ($mimeTypes !== []) {
            $mustClauses[] = [
                'bool' => [
                    'should' => [
                        ['terms' => ['mime_type' => $mimeTypes]],
                        ['terms' => ['mime_type.keyword' => $mimeTypes]],
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

    /**
     * Scope by ontology class URI (same as backoffice {@see SearchProxy::getAdvancedSearchQueryString}).
     */
    private function buildScopeClause(string $scopeClassUri): array
    {
        return $this->resourceQueryBlockSupport->buildStandardFieldMustClause(
            new QueryBlock('parent_classes', $scopeClassUri)
        );
    }

    private function buildDocumentSearchTokenClause(string $token): array
    {
        return $this->buildSearchTokenClause('search_tokens', $token);
    }

    private function buildSearchTokenClause(string $field, string $token): array
    {
        if (mb_strlen($token, 'UTF-8') >= self::PREFIX_MATCH_MIN_LENGTH) {
            return [
                'prefix' => [
                    $field => [
                        'value' => $token,
                        'case_insensitive' => true,
                    ],
                ],
            ];
        }

        return ['term' => [$field => $token]];
    }

    private function buildMetadataClause(string $propertyUri, string $value): array
    {
        $queryBlock = new QueryBlock($propertyUri, $value);
        $flatQueryBlock = new QueryBlock($propertyUri, $this->escapeFlatQueryStringTerm($value));
        $legacyOrExact = $this->nestedAttributesQueryService->buildCustomFieldSearchQuery(
            $queryBlock,
            $this->resourceQueryBlockSupport->buildFlatCustomMetadataQueryString($flatQueryBlock)
        );

        // Text criteria often need trailing-token match (e.g. label "47" → "mp3_47.mp3"),
        // while enum/URI values still match via exact term in $legacyOrExact.
        $trailingToken = $this->buildMetadataTrailingTokenClause($propertyUri, $value);

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

    private function buildMetadataTrailingTokenClause(string $propertyUri, string $value): array
    {
        $valueTokens = $this->assetSearchTokenizer->tokenize($value);
        if (trim($value) !== '' && $valueTokens === []) {
            return [
                'nested' => [
                    'path' => 'attributes',
                    'query' => ['match_none' => (object)[]],
                ],
            ];
        }

        $tokenClauses = array_map(function (string $token): array {
            return $this->buildSearchTokenClause('attributes.search_tokens', $token);
        }, $valueTokens);

        return [
            'nested' => [
                'path' => 'attributes',
                'query' => [
                    'bool' => [
                        'must' => array_merge(
                            [['term' => ['attributes.key' => $propertyUri]]],
                            $tokenClauses
                        ),
                    ],
                ],
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

    private function escapeFlatQueryStringTerm(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function includeAccessControlInQuery(): bool
    {
        return $this->useAclSpecification->isSatisfiedBy(
            IndexerInterface::ASSETS_INDEX,
            $this->permission,
            $this->sessionService->getCurrentUser()
        );
    }

    /**
     * @return list<string>
     */
    private function getAccessControlIdentifiers(): array
    {
        $identifiers = [];
        $currentUser = $this->sessionService->getCurrentUser();
        $identifiers[] = $currentUser->getIdentifier();
        foreach ($currentUser->getRoles() as $role) {
            $identifiers[] = $role;
        }

        return $identifiers;
    }
}
