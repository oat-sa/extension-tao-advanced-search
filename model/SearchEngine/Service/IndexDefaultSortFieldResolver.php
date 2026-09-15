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
 * Resolves the keyword field used when callers sort by id/_id.
 * Values come from each index conf's {@code defaultSortField} (not sent to ES).
 * Reads the provider on each resolve so {@see IndexConfigurationProvider::setIndexFile}
 * is picked up without rebuilding the resolver.
 */
class IndexDefaultSortFieldResolver
{
    private const FALLBACK = 'updated_at.raw';

    private IndexConfigurationProvider $indexConfigurationProvider;

    public function __construct(IndexConfigurationProvider $indexConfigurationProvider)
    {
        $this->indexConfigurationProvider = $indexConfigurationProvider;
    }

    public function resolveForIndex(string $index): string
    {
        $fieldsByIndex = $this->fieldsByIndex();

        if (isset($fieldsByIndex[$index])) {
            return $fieldsByIndex[$index];
        }

        foreach ($fieldsByIndex as $logical => $field) {
            if (str_ends_with($index, $logical)) {
                return $field;
            }
        }

        return self::FALLBACK;
    }

    /**
     * @return array<string, string>
     */
    private function fieldsByIndex(): array
    {
        $fieldsByIndex = [];

        foreach ($this->indexConfigurationProvider->getIndexes() as $def) {
            if (!is_array($def) || !isset($def['index'], $def['defaultSortField'])) {
                continue;
            }

            $fieldsByIndex[$def['index']] = $def['defaultSortField'];
        }

        return $fieldsByIndex;
    }
}
