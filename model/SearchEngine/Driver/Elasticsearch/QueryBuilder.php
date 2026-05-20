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

namespace oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch;

use oat\generis\model\data\permission\PermissionInterface;
use oat\oatbox\session\SessionService;
use oat\taoAdvancedSearch\model\Metadata\Service\AdvancedSearchSettingsService;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\QueryBlock;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use oat\taoAdvancedSearch\model\SearchEngine\Service\LegacyResourceQueryConditionsBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesFeature;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceQueryBlockSupport;
use oat\taoAdvancedSearch\model\SearchEngine\Service\StructuredResourceSearchQueryBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Specification\UseAclSpecification;
use Psr\Log\LoggerInterface;
use tao_helpers_Uri;
use common_Utils;

class QueryBuilder
{
    private const QUERY_STRING_REPLACEMENTS = [
        '"' => '',
        '\'' => '',
        '\\' => '\\\\',
    ];

    private const READ_ACCESS_FIELD = 'read_access';

    public const STRUCTURE_TO_INDEX_MAP = [
        'results' => IndexerInterface::DELIVERY_RESULTS_INDEX,
        'delivery' => IndexerInterface::DELIVERIES_INDEX,
        'groups' => IndexerInterface::GROUPS_INDEX,
        'items' => IndexerInterface::ITEMS_INDEX,
        'tests' => IndexerInterface::TESTS_INDEX,
        'TestTaker' => IndexerInterface::TEST_TAKERS_INDEX,
        'taoMediaManager' => IndexerInterface::ASSETS_INDEX,
        IndexerInterface::PROPERTY_LIST => IndexerInterface::PROPERTY_LIST,
    ];

    /** @var UseAclSpecification */
    private $useAclSpecification;

    /** @var LoggerInterface */
    private $logger;

    /** @var PermissionInterface */
    private $permission;

    /** @var SessionService */
    private $sessionService;

    /** @var IndexPrefixer */
    private $prefixer;

    private NestedAttributesFeature $nestedAttributesFeature;
    private LegacyResourceQueryConditionsBuilder $legacyResourceQueryConditionsBuilder;
    private StructuredResourceSearchQueryBuilder $structuredResourceSearchQueryBuilder;
    private ResourceQueryBlockSupport $resourceQueryBlockSupport;

    public function __construct(
        LoggerInterface $logger,
        PermissionInterface $permission,
        SessionService $sessionService,
        IndexPrefixer $prefixer,
        UseAclSpecification $useAclSpecification,
        NestedAttributesFeature $nestedAttributesFeature,
        LegacyResourceQueryConditionsBuilder $legacyResourceQueryConditionsBuilder,
        StructuredResourceSearchQueryBuilder $structuredResourceSearchQueryBuilder,
        ResourceQueryBlockSupport $resourceQueryBlockSupport
    ) {
        $this->logger = $logger;
        $this->permission = $permission;
        $this->sessionService = $sessionService;
        $this->prefixer = $prefixer;
        $this->useAclSpecification = $useAclSpecification;
        $this->nestedAttributesFeature = $nestedAttributesFeature;
        $this->legacyResourceQueryConditionsBuilder = $legacyResourceQueryConditionsBuilder;
        $this->structuredResourceSearchQueryBuilder = $structuredResourceSearchQueryBuilder;
        $this->resourceQueryBlockSupport = $resourceQueryBlockSupport;
    }

    public function getSearchParams(
        string $queryString,
        string $type,
        int $start,
        int $count,
        string $order,
        string $dir
    ): array {
        $queryString = str_replace(
            array_keys(self::QUERY_STRING_REPLACEMENTS),
            array_values(self::QUERY_STRING_REPLACEMENTS),
            $queryString
        );

        $queryString = htmlspecialchars_decode($queryString);

        $blocks = preg_split('/( AND )/i', $queryString);
        $index = $this->getIndexByType($type);
        $query = [
            'query' => $this->buildRootQuery($index, $blocks),
            'sort' => [
                $order => [
                    'order' => $dir,
                    'missing' => '_last',
                    'unmapped_type' => 'long',
                ],
                AdvancedSearchSettingsService::DEFAULT_SORT_COLUMN => [
                    'order' => $dir,
                    'missing' => '_last',
                    'unmapped_type' => 'long',
                ],
            ],
        ];

        $params = [
            'index' => $index,
            'size' => $count,
            'from' => $start,
            'client' => ['ignore' => 404],
            'body' => json_encode($query),
        ];

        $this->logger->debug('Input Query: ' . $queryString);
        $this->logger->debug('Elastic Query: ' . json_encode($params));

        return $params;
    }

    /**
     * @param string[] $blocks
     */
    private function buildRootQuery(string $index, array $blocks): array
    {
        if ($index === IndexerInterface::DELIVERY_RESULTS_INDEX) {
            return $this->buildLegacyFlatQueryString($this->getResultsQueryStringFragments($blocks));
        }

        if ($this->nestedAttributesFeature->shouldUseNestedAttributes($index)) {
            $mustClauses = $this->structuredResourceSearchQueryBuilder->buildMustClauses($blocks);
            if ($this->includeAccessData($index)) {
                $mustClauses[] = $this->resourceQueryBlockSupport->buildAccessControlMustClause(
                    $this->getAccessControlIdentifiers()
                );
            }

            return $this->buildStructuredBoolMustQuery($mustClauses);
        }

        $fragments = $this->legacyResourceQueryConditionsBuilder->build($blocks);
        if ($this->includeAccessData($index)) {
            $fragments[] = $this->buildLegacyAccessConditions();
        }

        return $this->buildLegacyFlatQueryString($fragments);
    }

    /**
     * @param list<string> $fragments
     */
    private function buildLegacyFlatQueryString(array $fragments): array
    {
        if ($fragments === []) {
            return ['match_all' => (object)[]];
        }

        return [
            'query_string' => [
                'default_operator' => 'AND',
                'query' => implode(' AND ', $fragments),
            ],
        ];
    }

    /**
     * @param list<array> $mustClauses
     */
    private function buildStructuredBoolMustQuery(array $mustClauses): array
    {
        if ($mustClauses === []) {
            return ['match_all' => (object)[]];
        }

        return [
            'bool' => [
                'must' => $mustClauses,
            ],
        ];
    }

    /**
     * @param string[] $blocks
     *
     * @return list<string>
     */
    private function getResultsQueryStringFragments(array $blocks): array
    {
        $fragments = [];

        foreach ($blocks as $block) {
            $block = $this->parseBlock($block);
            if ($block->getField() === 'parent_classes') {
                continue;
            }

            $fragments[] = sprintf('("%s")', $block->getTerm());
        }

        return $fragments;
    }

    private function getIndexByType(string $type): string
    {
        if (isset(self::STRUCTURE_TO_INDEX_MAP[$type])) {
            return $this->prefixer->prefix(self::STRUCTURE_TO_INDEX_MAP[$type]);
        }

        return IndexerInterface::UNCLASSIFIEDS_DOCUMENTS_INDEX;
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

    private function buildLegacyAccessConditions(): string
    {
        return sprintf(
            '(%s:("%s"))',
            self::READ_ACCESS_FIELD,
            implode('" OR "', $this->getAccessControlIdentifiers())
        );
    }

    private function includeAccessData(string $index): bool
    {
        return $this->useAclSpecification->isSatisfiedBy(
            $index,
            $this->permission,
            $this->sessionService->getCurrentUser()
        );
    }

    private function parseBlock(string $block): QueryBlock
    {
        if (common_Utils::isUri($block)) {
            return new QueryBlock(null, $block);
        }

        preg_match('/((?P<field>[^:]*):)?(?P<term>.*)/', $block, $matches);

        $field = trim($matches['field']);

        if (!common_Utils::isUri(tao_helpers_Uri::decode($field))) {
            $field = strtolower($field);
        }

        return new QueryBlock($field, trim($matches['term']));
    }
}
