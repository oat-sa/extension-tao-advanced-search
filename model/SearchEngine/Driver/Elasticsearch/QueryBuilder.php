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
use oat\taoAdvancedSearch\model\SearchEngine\Specification\UseAclSpecification;
use Psr\Log\LoggerInterface;
use tao_helpers_Uri;
use common_Utils;

class QueryBuilder
{
    private const QUERY_STRING_REPLACEMENTS = [
        '"' => '',
        '\'' => '',
        '\\' => '\\\\'
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

    private const STANDARD_FIELDS = [
        'class',
        'parent_classes',
        'content',
        'label',
        'model',
        'login',
        'delivery',
        'test_taker',
        'test_taker_name',
        'delivery_execution',
        'custom_tag',
        'context_id',
        'context_label',
        'resource_link_id',
        'item_uris'
    ];

    private const CUSTOM_FIELDS = [
        'HTMLArea',
        'TextArea',
        'TextBox',
        'ComboBox',
        'CheckBox',
        'RadioBox',
        'SearchTextBox',
        'SearchDropdown',
        'Readonly',
    ];

    private const LOGIC_MODIFIERS = [
        'and' => 'LOGIC_AND',
        'or' => 'LOGIC_OR',
        'not' => 'LOGIC_NOT'
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

    public function __construct(
        LoggerInterface $logger,
        PermissionInterface $permission,
        SessionService $sessionService,
        IndexPrefixer $prefixer,
        UseAclSpecification $useAclSpecification
    ) {
        $this->logger = $logger;
        $this->permission = $permission;
        $this->sessionService = $sessionService;
        $this->prefixer = $prefixer;
        $this->useAclSpecification = $useAclSpecification;
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
        $conditions = $this->buildConditions($index, $blocks);

        $query = [
            'query' => [
                'query_string' =>
                    [
                        'default_operator' => 'AND',
                        'query' => implode(' AND ', $conditions)
                    ]
            ],
            'sort' => [
                $order => [
                    'order' => $dir,
                    'missing' => '_last',
                    'unmapped_type' => 'long'
                ],
                AdvancedSearchSettingsService::DEFAULT_SORT_COLUMN => [
                    'order' => $dir,
                    'missing' => '_last',
                    'unmapped_type' => 'long',
                ],
            ]
        ];

        $params = [
            'index' => $index,
            'size' => $count,
            'from' => $start,
            'client' => ['ignore' => 404],
            'body' => json_encode($query)
        ];

        $this->logger->debug('Input Query: ' . $queryString);
        $this->logger->debug('Elastic Query: ' . json_encode($params));

        return $params;
    }

    private function buildConditions(string $index, array $blocks): array
    {
        $conditions = $this->buildConditionsByType($index, $blocks);

        if ($this->includeAccessData($index)) {
            $conditions[] = $this->buildAccessConditions();
        }

        return $conditions;
    }

    /**
     * Use only simple input
     * @param string[] $blocks
     * @return string[]
     */
    private function getResultsConditions(array $blocks): array
    {
        $conditions = [];

        foreach ($blocks as $block) {
            $block = $this->parseBlock($block);
            if ($block->getField() === 'parent_classes') {
                continue;
            }

            $conditions[] = sprintf('("%s")', $block->getTerm());
        }

        return $conditions;
    }

    private function buildConditionsByType(string $type, array $blocks): array
    {
        if ($type === IndexerInterface::DELIVERY_RESULTS_INDEX) {
            return $this->getResultsConditions($blocks);
        }

        return $this->getResourceConditions($blocks);
    }

    private function containsLogicalModifier(string $block): bool
    {
        foreach (self::LOGIC_MODIFIERS as $logicModifier) {
            if (strpos($block, $logicModifier) !== false) {
                return true;
            }
        }

        return false;
    }

    private function buildLogicCondition(string $block): ?string
    {
        if (strpos($block, self::LOGIC_MODIFIERS['and']) !== false) {
            $logicBlocks = preg_split('/( ' . self::LOGIC_MODIFIERS['and'] . ' )/i', $block);
            $conditions = array_map([$this, 'buildConditionFromTheBlock'], $logicBlocks);

            return sprintf('(%s)', implode(' AND ', $conditions));
        }

        if (strpos($block, self::LOGIC_MODIFIERS['or']) !== false) {
            $logicBlocks = preg_split('/( ' . self::LOGIC_MODIFIERS['or'] . ' )/i', $block);
            $conditions = array_map([$this,'buildConditionFromTheBlock'], $logicBlocks);

            return sprintf('(%s)', implode(' OR ', $conditions));
        }

        if (strpos($block, self::LOGIC_MODIFIERS['not']) !== false) {
            $logicBlocks = preg_split('/( ' . self::LOGIC_MODIFIERS['not'] . ' )/i', $block);
            $conditions = array_map([$this,'buildConditionFromTheBlock'], $logicBlocks);

            return sprintf('NOT (%s)', implode(' OR ', $conditions));
        }

        return null;
    }

    private function getResourceConditions(array $blocks): array
    {

        $conditions = [];

        foreach ($blocks as $block) {
            if ($this->containsLogicalModifier($block)) {
                $conditions[] =  $this->buildLogicCondition($block);
            } else {
                $conditions[] =  $this->buildConditionFromTheBlock($block);
            }
        }

        return $conditions;
    }

    private function buildConditionFromTheBlock(string $block): string
    {
        $queryBlock = $this->parseBlock($block);
        if (empty($queryBlock->getField())) {
            return sprintf('("%s")', $queryBlock->getTerm());
        } elseif ($this->isStandardField($queryBlock->getField())) {
            return sprintf('(%s:"%s")', $queryBlock->getField(), $queryBlock->getTerm());
        } else {
            return $this->buildCustomConditions($queryBlock);
        }
        return '';
    }

    private function isStandardField(string $field): bool
    {
        return in_array(strtolower($field), self::STANDARD_FIELDS);
    }


    private function getIndexByType(string $type): string
    {
        if (isset(self::STRUCTURE_TO_INDEX_MAP[$type])) {
            return $this->prefixer->prefix(self::STRUCTURE_TO_INDEX_MAP[$type]);
        }

        return IndexerInterface::UNCLASSIFIEDS_DOCUMENTS_INDEX;
    }

    private function buildCustomConditions(QueryBlock $queryBlock): string
    {
        $conditions = [];

        foreach (self::CUSTOM_FIELDS as $customField) {
            $conditions[] = sprintf('%s_%s:"%s"', $customField, $queryBlock->getField(), $queryBlock->getTerm());
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function buildAccessConditions(): string
    {
        $conditions = [];

        $currentUser = $this->sessionService->getCurrentUser();

        $conditions[] = $currentUser->getIdentifier();
        foreach ($currentUser->getRoles() as $role) {
            $conditions[] = $role;
        }

        return sprintf(
            '(%s:("%s"))',
            self::READ_ACCESS_FIELD,
            implode('" OR "', $conditions)
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

        if (!$this->isUri($field)) {
            $field = strtolower($field);
        }

        return new QueryBlock($field, trim($matches['term']));
    }

    private function isUri(string $term): bool
    {
        return common_Utils::isUri(tao_helpers_Uri::decode($term));
    }
}
