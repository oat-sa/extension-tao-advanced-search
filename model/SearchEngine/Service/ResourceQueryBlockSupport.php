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
use tao_helpers_Uri;
use common_Utils;

/**
 * Shared parsing and query fragment builders for resource search.
 */
class ResourceQueryBlockSupport
{
    public const STANDARD_FIELDS = [
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
        'item_uris',
    ];

    public const CUSTOM_FIELDS = [
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

    public const LOGIC_MODIFIERS = [
        'and' => 'LOGIC_AND',
        'or' => 'LOGIC_OR',
        'not' => 'LOGIC_NOT',
    ];

    public function parseBlock(string $block): QueryBlock
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

    public function buildConditionFromTheBlock(string $block): string
    {
        $queryBlock = $this->parseBlock($block);
        if (empty($queryBlock->getField())) {
            return sprintf('("%s")', $queryBlock->getTerm());
        }

        if ($this->isStandardField($queryBlock->getField())) {
            return sprintf('(%s:"%s")', $queryBlock->getField(), $queryBlock->getTerm());
        }

        return $this->buildFlatCustomMetadataQueryString($queryBlock);
    }

    /**
     * Lucene OR of dynamic flat custom metadata field names. Used only for unreindexed-document compatibility.
     */
    public function buildFlatCustomMetadataQueryString(QueryBlock $queryBlock): string
    {
        $conditions = [];

        foreach (self::CUSTOM_FIELDS as $customField) {
            $conditions[] = sprintf('%s_%s:"%s"', $customField, $queryBlock->getField(), $queryBlock->getTerm());
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    public function isStandardField(string $field): bool
    {
        return in_array(strtolower($field), self::STANDARD_FIELDS, true);
    }

    public function isCustomFieldBlock(QueryBlock $queryBlock): bool
    {
        return !empty($queryBlock->getField()) && !$this->isStandardField($queryBlock->getField());
    }

    public function containsLogicalModifier(string $block): bool
    {
        foreach (self::LOGIC_MODIFIERS as $logicModifier) {
            if (strpos($block, $logicModifier) !== false) {
                return true;
            }
        }

        return false;
    }

    public function buildLogicCondition(string $block): ?string
    {
        if (strpos($block, self::LOGIC_MODIFIERS['and']) !== false) {
            $logicBlocks = preg_split('/( ' . self::LOGIC_MODIFIERS['and'] . ' )/i', $block);
            $conditions = array_map([$this, 'buildConditionFromTheBlock'], $logicBlocks);

            return sprintf('(%s)', implode(' AND ', $conditions));
        }

        if (strpos($block, self::LOGIC_MODIFIERS['or']) !== false) {
            $logicBlocks = preg_split('/( ' . self::LOGIC_MODIFIERS['or'] . ' )/i', $block);
            $conditions = array_map([$this, 'buildConditionFromTheBlock'], $logicBlocks);

            return sprintf('(%s)', implode(' OR ', $conditions));
        }

        if (strpos($block, self::LOGIC_MODIFIERS['not']) !== false) {
            $logicBlocks = preg_split('/( ' . self::LOGIC_MODIFIERS['not'] . ' )/i', $block);
            $conditions = array_map([$this, 'buildConditionFromTheBlock'], $logicBlocks);

            return sprintf('NOT (%s)', implode(' OR ', $conditions));
        }

        return null;
    }

    public function isLogicalCustomCondition(string $block): bool
    {
        $parts = preg_split('/( LOGIC_AND | LOGIC_OR | LOGIC_NOT )/i', $block);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (!$this->isCustomFieldBlock($this->parseBlock($part))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Structured {@code bool.must} clause for a standard (non-custom) field predicate.
     */
    public function buildStandardFieldMustClause(QueryBlock $queryBlock): array
    {
        $field = $queryBlock->getField();
        $term = $queryBlock->getTerm();

        return [
            'bool' => [
                'should' => [
                    ['term' => [$field . '.raw' => $term]],
                    ['match_phrase' => [$field => $term]],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    /**
     * Structured root-level clause for fieldless terms (standard index fields only, not flat custom metadata).
     */
    public function buildFieldlessRootMustClause(string $term): array
    {
        return [
            'multi_match' => [
                'query' => $term,
                'fields' => self::STANDARD_FIELDS,
                'type' => 'phrase',
                'operator' => 'and',
            ],
        ];
    }

    /**
     * @param array[] $subClauses
     */
    public function wrapLogicalMustClauses(array $subClauses): array
    {
        return ['bool' => ['must' => $subClauses]];
    }

    /**
     * @param array[] $subClauses
     */
    public function wrapLogicalShouldClauses(array $subClauses): array
    {
        return ['bool' => ['should' => $subClauses, 'minimum_should_match' => 1]];
    }

    /**
     * @param array[] $subClauses
     */
    public function wrapLogicalMustNotClauses(array $subClauses): array
    {
        return ['bool' => ['must_not' => $subClauses]];
    }

    /**
     * Structured ACL filter equivalent to master {@code (read_access:("id" OR "role"))}.
     *
     * @param list<string> $identifiers
     */
    public function buildAccessControlMustClause(array $identifiers): array
    {
        return [
            'terms' => [
                'read_access' => array_values($identifiers),
            ],
        ];
    }

    private function isUri(string $term): bool
    {
        return common_Utils::isUri(tao_helpers_Uri::decode($term));
    }
}
