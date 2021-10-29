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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Metadata\Specification;

class PropertyAllowedSpecification
{
    public const CONFIG_BLACK_LIST = 'ADVANCED_SEARCH_METADATA_BLACK_LIST';

    private const SYSTEM_PROPERTIES = [
        'http://www.tao.lu/Ontologies/TAOItem.rdf#ItemModel',
        'http://www.w3.org/2000/01/rdf-schema#comment',
        'http://www.w3.org/2000/01/rdf-schema#isDefinedBy',
        'http://www.w3.org/2000/01/rdf-schema#seeAlso',
        'http://www.w3.org/1999/02/22-rdf-syntax-ns#value',
        'http://www.tao.lu/Ontologies/TAOTest.rdf#TestModel',
        'http://www.tao.lu/Ontologies/TAOTest.rdf#TestTestModel',
        'http://www.tao.lu/Ontologies/generis.rdf#userDefLg',
    ];

    /** @var array */
    private $blackListUris;

    public function __construct(?array $blackListUris = [])
    {
        $this->blackListUris = array_merge($blackListUris, self::SYSTEM_PROPERTIES);
    }

    public function isSatisfiedBy(string $propertyUri): bool
    {
        return !in_array($propertyUri, $this->blackListUris, true);
    }
}
