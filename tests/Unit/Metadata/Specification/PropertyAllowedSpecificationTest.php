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

namespace oat\taoAdvancedSearch\tests\Unit\model\Metadata\Normalizer;

use oat\generis\test\TestCase;
use oat\taoAdvancedSearch\model\Metadata\Specification\PropertyAllowedSpecification;

class PropertyAllowedSpecificationTest extends TestCase
{
    private const PROPERTY_FORBIDDEN = 'forbidden';

    /** @var PropertyAllowedSpecification */
    private $subject;

    public function setUp(): void
    {
        $this->subject = new PropertyAllowedSpecification(
            [
                self::PROPERTY_FORBIDDEN,
            ]
        );
    }

    /**
     * @dataProvider getDataProvider
     */
    public function testIsSatisfiedBy(bool $expected, string $property): void
    {
        $this->assertSame($expected, $this->subject->isSatisfiedBy($property));
    }

    public function getDataProvider(): array
    {
        return [
            'with allowed properties' => [
                true,
                'anyUriHere'
            ],
            'with not allowed properties' => [
                false,
                self::PROPERTY_FORBIDDEN
            ],
            'with not allowed system properties' => [
                false,
                'http://www.tao.lu/Ontologies/TAOItem.rdf#ItemModel'
            ],
        ];
    }
}
