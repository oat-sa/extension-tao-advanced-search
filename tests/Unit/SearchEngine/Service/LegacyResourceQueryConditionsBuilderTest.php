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

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine\Service;

use oat\taoAdvancedSearch\model\SearchEngine\Service\LegacyResourceQueryConditionsBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceQueryBlockSupport;
use PHPUnit\Framework\TestCase;

class LegacyResourceQueryConditionsBuilderTest extends TestCase
{
    public function testBuildReturnsOnlyLegacyQueryStringFragments(): void
    {
        $sut = new LegacyResourceQueryConditionsBuilder(new ResourceQueryBlockSupport());

        $conditions = $sut->build(['label:test', 'custom_field:foo']);

        $this->assertCount(2, $conditions);
        $this->assertStringContainsString('label:"test"', $conditions[0]);
        $this->assertStringContainsString('HTMLArea_custom_field:"foo"', $conditions[1]);
    }
}
