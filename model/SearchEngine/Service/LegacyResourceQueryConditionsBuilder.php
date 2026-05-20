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

/**
 * Pre-nested-attributes query condition building: a single {@code query_string} per search (master behaviour).
 * Used when nested attributes are disabled via feature flag or the index is not on the nested mapping list.
 */
class LegacyResourceQueryConditionsBuilder
{
    private ResourceQueryBlockSupport $blockSupport;

    public function __construct(ResourceQueryBlockSupport $blockSupport)
    {
        $this->blockSupport = $blockSupport;
    }

    /**
     * @param string[] $blocks
     *
     * @return list<string> Lucene fragments AND-joined into the root {@code query_string}
     */
    public function build(array $blocks): array
    {
        $fragments = [];

        foreach ($blocks as $block) {
            if ($this->blockSupport->containsLogicalModifier($block)) {
                $legacyLogicalCondition = $this->blockSupport->buildLogicCondition($block);
                if ($legacyLogicalCondition !== null) {
                    $fragments[] = $legacyLogicalCondition;
                }
                continue;
            }

            $fragments[] = $this->blockSupport->buildConditionFromTheBlock($block);
        }

        return $fragments;
    }
}
