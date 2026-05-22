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

/**
 * New-format resource search: structured Elasticsearch DSL with nested {@code attributes}.
 *
 * Behaviourally equivalent to the legacy flat {@code query_string} path for the same user input;
 * only unreindexed flat custom metadata uses a temporary {@code query_string} compatibility clause.
 */
class StructuredResourceSearchQueryBuilder
{
    private ResourceQueryBlockSupport $blockSupport;
    private NestedAttributesQueryService $nestedAttributesQueryService;

    public function __construct(
        ResourceQueryBlockSupport $blockSupport,
        NestedAttributesQueryService $nestedAttributesQueryService
    ) {
        $this->blockSupport = $blockSupport;
        $this->nestedAttributesQueryService = $nestedAttributesQueryService;
    }

    /**
     * @param string[] $blocks
     *
     * @return list<array> Clauses combined with {@code bool.must} at the root
     */
    public function buildMustClauses(array $blocks): array
    {
        $mustClauses = [];

        foreach ($blocks as $block) {
            if ($this->blockSupport->containsLogicalModifier($block)) {
                if ($this->blockSupport->isLogicalCustomCondition($block)) {
                    $mustClauses[] = $this->buildLogicalCustomCondition($block);
                    continue;
                }

                $structuredLogicalCondition = $this->buildLogicalStandardCondition($block);
                if ($structuredLogicalCondition !== null) {
                    $mustClauses[] = $structuredLogicalCondition;
                    continue;
                }
            }

            $queryBlock = $this->blockSupport->parseBlock($block);
            if ($this->blockSupport->isCustomFieldBlock($queryBlock)) {
                $mustClauses[] = $this->nestedAttributesQueryService->buildCustomFieldSearchQuery(
                    $queryBlock,
                    $this->blockSupport->buildFlatCustomMetadataQueryString($queryBlock)
                );
                continue;
            }

            $term = $queryBlock->getTerm();
            if (empty($queryBlock->getField()) && $term !== '') {
                $mustClauses[] = $this->nestedAttributesQueryService->buildFieldlessSearchQuery(
                    $term,
                    $this->blockSupport->buildFieldlessRootMustClause($term)
                );
                continue;
            }

            $mustClauses[] = $this->blockSupport->buildStandardFieldMustClause($queryBlock);
        }

        return $mustClauses;
    }

    private function buildLogicalStandardCondition(string $block): ?array
    {
        if (stripos($block, ResourceQueryBlockSupport::LOGIC_MODIFIERS['and']) !== false) {
            $logicBlocks = preg_split(
                '/( ' . ResourceQueryBlockSupport::LOGIC_MODIFIERS['and'] . ' )/i',
                $block
            );

            return $this->blockSupport->wrapLogicalMustClauses(
                array_map(fn (string $part): array => $this->buildClauseFromBlock($part), $logicBlocks)
            );
        }

        if (stripos($block, ResourceQueryBlockSupport::LOGIC_MODIFIERS['or']) !== false) {
            $logicBlocks = preg_split(
                '/( ' . ResourceQueryBlockSupport::LOGIC_MODIFIERS['or'] . ' )/i',
                $block
            );

            return $this->blockSupport->wrapLogicalShouldClauses(
                array_map(fn (string $part): array => $this->buildClauseFromBlock($part), $logicBlocks)
            );
        }

        if (stripos($block, ResourceQueryBlockSupport::LOGIC_MODIFIERS['not']) !== false) {
            $logicBlocks = preg_split(
                '/( ' . ResourceQueryBlockSupport::LOGIC_MODIFIERS['not'] . ' )/i',
                $block
            );

            return $this->blockSupport->wrapLogicalMustNotClauses(
                array_map(fn (string $part): array => $this->buildClauseFromBlock($part), $logicBlocks)
            );
        }

        return null;
    }

    private function buildLogicalCustomCondition(string $block): array
    {
        if (stripos($block, ResourceQueryBlockSupport::LOGIC_MODIFIERS['and']) !== false) {
            $logicBlocks = preg_split(
                '/( ' . ResourceQueryBlockSupport::LOGIC_MODIFIERS['and'] . ' )/i',
                $block
            );
            $conditions = array_map(
                fn (string $part): array => $this->buildCustomFieldClauseFromBlock($part),
                $logicBlocks
            );

            return ['bool' => ['must' => $conditions]];
        }

        if (stripos($block, ResourceQueryBlockSupport::LOGIC_MODIFIERS['or']) !== false) {
            $logicBlocks = preg_split(
                '/( ' . ResourceQueryBlockSupport::LOGIC_MODIFIERS['or'] . ' )/i',
                $block
            );
            $conditions = array_map(
                fn (string $part): array => $this->buildCustomFieldClauseFromBlock($part),
                $logicBlocks
            );

            return ['bool' => ['should' => $conditions, 'minimum_should_match' => 1]];
        }

        if (stripos($block, ResourceQueryBlockSupport::LOGIC_MODIFIERS['not']) !== false) {
            $logicBlocks = preg_split(
                '/( ' . ResourceQueryBlockSupport::LOGIC_MODIFIERS['not'] . ' )/i',
                $block
            );
            $conditions = array_map(
                fn (string $part): array => $this->buildCustomFieldClauseFromBlock($part),
                $logicBlocks
            );

            return ['bool' => ['must_not' => $conditions]];
        }

        return $this->buildCustomFieldClauseFromBlock($block);
    }

    private function buildClauseFromBlock(string $block): array
    {
        $queryBlock = $this->blockSupport->parseBlock($block);

        if ($this->blockSupport->isCustomFieldBlock($queryBlock)) {
            return $this->buildCustomFieldClauseFromBlock($block);
        }

        $term = $queryBlock->getTerm();
        if (empty($queryBlock->getField()) && $term !== '') {
            return $this->nestedAttributesQueryService->buildFieldlessSearchQuery(
                $term,
                $this->blockSupport->buildFieldlessRootMustClause($term)
            );
        }

        return $this->blockSupport->buildStandardFieldMustClause($queryBlock);
    }

    private function buildCustomFieldClauseFromBlock(string $block): array
    {
        $queryBlock = $this->blockSupport->parseBlock($block);

        return $this->nestedAttributesQueryService->buildCustomFieldSearchQuery(
            $queryBlock,
            $this->blockSupport->buildFlatCustomMetadataQueryString($queryBlock)
        );
    }
}
