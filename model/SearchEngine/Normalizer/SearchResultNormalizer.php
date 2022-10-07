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
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\SearchEngine\Normalizer;

use DateTime;
use oat\tao\model\search\index\DocumentBuilder\PropertyIndexReferenceFactory;
use oat\tao\model\search\ResultSet;
use oat\taoAdvancedSearch\model\SearchEngine\SearchResult;
use tao_helpers_Uri;

class SearchResultNormalizer
{
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
                if (!$this->isKeyAllowed($resultKey)) {
                    continue;
                }

                if (strpos($resultKey, PropertyIndexReferenceFactory::RAW_SUFFIX) !== false) {
                    $originalKey = str_replace(PropertyIndexReferenceFactory::RAW_SUFFIX, '', $resultKey);

                    if (!$this->isKeyAllowed($originalKey)) {
                        continue;
                    }

                    $newResult[$this->extractId($originalKey)] = $resultValue;

                    continue;
                }

                if ($this->isCalendar($resultKey)) {
                    $resultValue = is_array($resultValue) ? current($resultValue) : $resultValue;
                    $resultValue = (new DateTime('now'))->setTimestamp((int)$resultValue)->format('d/m/Y - H:i');
                }

                $newResult[$this->extractId($resultKey)] = $resultValue;
            }

            $out[] = $newResult;
        }

        return new SearchResult($out, $resultSet->getTotalCount());
    }

    private function isCalendar(string $key): bool
    {
        return $key === 'updated_at' || strpos($key, 'Calendar_') === 0;
    }

    private function isKeyAllowed(string $key): bool
    {
        return !in_array($key, self::OMIT_PROPERTIES, true);
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
