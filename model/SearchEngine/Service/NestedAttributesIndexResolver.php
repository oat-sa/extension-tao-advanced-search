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

use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;

/**
 * Resolves whether an Elasticsearch index name uses nested {@code attributes} for custom metadata.
 *
 * @see IndexerInterface::INDEXES_USING_NESTED_ATTRIBUTES
 */
class NestedAttributesIndexResolver
{
    public function usesNestedAttributesMapping(string $indexName): bool
    {
        foreach (IndexerInterface::INDEXES_USING_NESTED_ATTRIBUTES as $index) {
            if ($indexName === $index || str_ends_with($indexName, $index)) {
                return true;
            }
        }

        return false;
    }
}
