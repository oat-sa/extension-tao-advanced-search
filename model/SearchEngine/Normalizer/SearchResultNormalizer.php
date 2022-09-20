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

namespace oat\taoAdvancedSearch\model\SearchEngine\Normalizer;

use oat\tao\model\search\ResultSet;
use oat\taoAdvancedSearch\model\SearchEngine\SearchResult;
use tao_helpers_Uri;

class SearchResultNormalizer
{
    private const PLAIN_TEXT_KEY = '_raw';
    private const OMIT_PROPERTIES = [
        'read_access',
        'TextBox_http_2_www_0_w3_0_org_1_2000_1_01_1_rdf-schema_3_label',
        'RadioBox_http_2_www_0_tao_0_lu_1_Ontologies_1_TAOItem_0_rdf_3_ItemModel',
        'type',
        'parent_classes',
        'class',
        'content',
        'model'
    ];

    public function normalizeByByResultSet(ResultSet $resultSet): SearchResult
    {
        $out = [];

        foreach ($resultSet as $result) {
            $newResult = [];

            foreach ($result as $resultKey => $resultValue) {
                if (in_array($resultKey, self::OMIT_PROPERTIES)) {
                    continue;
                }

                if (strpos($resultKey, self::PLAIN_TEXT_KEY) !== false) {
                    $originalKey = str_replace(self::PLAIN_TEXT_KEY, '', $resultKey);

                    if (in_array($originalKey, self::OMIT_PROPERTIES)) {
                        continue;
                    }

                    $newResult[$this->extractId($originalKey)] = $resultValue;

                    continue;
                }

                $newResult[$this->extractId($resultKey)] = $resultValue;
            }

            $out[] = $newResult;
        }

        return new SearchResult($out, $resultSet->getTotalCount());
    }

    private function extractId(string $key): string
    {
        $position = strpos($key, 'http');

        if ($position === false) {
            return $key;
        }

        return tao_helpers_Uri::decode(substr($key, $position));
    }
}
