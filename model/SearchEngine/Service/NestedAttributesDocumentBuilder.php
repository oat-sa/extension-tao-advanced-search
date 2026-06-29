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

use oat\tao\model\search\index\DocumentBuilder\PropertyIndexReferenceFactory;

/**
 * Converts legacy flat dynamic property fields into nested {@code attributes} rows for Elasticsearch.
 */
class NestedAttributesDocumentBuilder
{
    /**
     * @param array<string, mixed> $dynamicProperties
     * @return list<array{key: string, type: string, value: list<string>, raw_value?: list<string>|string}>
     */
    public function buildFromDynamicProperties(array $dynamicProperties): array
    {
        $attributes = [];
        $rawSuffix = PropertyIndexReferenceFactory::RAW_SUFFIX;

        foreach ($dynamicProperties as $fieldName => $values) {
            if (!is_array($values) || strpos($fieldName, '_') === false) {
                continue;
            }

            [$type, $key] = explode('_', $fieldName, 2);
            if ($key === '' || substr($key, -strlen($rawSuffix)) === $rawSuffix) {
                continue;
            }

            $rawFieldName = $fieldName . $rawSuffix;
            $rawValues = [];
            if (isset($dynamicProperties[$rawFieldName]) && is_array($dynamicProperties[$rawFieldName])) {
                $rawValues = array_values($dynamicProperties[$rawFieldName]);
            }

            $stringValues = array_map(static fn ($v): string => (string) $v, array_values($values));
            if ($stringValues === []) {
                continue;
            }

            $row = [
                'key' => $key,
                'type' => $type,
                'value' => $stringValues,
            ];

            $rawPayload = $this->resolveRawValuesForIndexedAttribute($rawValues, $stringValues);
            if ($rawPayload !== null) {
                $row['raw_value'] = $rawPayload;
            }

            $attributes[] = $row;
        }

        return $attributes;
    }

    /**
     * Aligns parallel {@see PropertyIndexReferenceFactory::createRaw} values with indexed {@code value} entries.
     *
     * @param list<string> $rawValues
     * @param list<string> $stringValues
     * @return list<string>|string|null
     */
    private function resolveRawValuesForIndexedAttribute(array $rawValues, array $stringValues): array|string|null
    {
        if ($rawValues === []) {
            return null;
        }

        if (count($rawValues) === count($stringValues)) {
            $parts = array_map(static fn ($v): string => (string) $v, array_values($rawValues));

            return count($parts) === 1 ? $parts[0] : $parts;
        }

        if (count($rawValues) === 1) {
            return (string) $rawValues[0];
        }

        return null;
    }
}
