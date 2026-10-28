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

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine\Service;

use oat\taoAdvancedSearch\model\SearchEngine\QueryBlock;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesQueryService;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ResourceQueryBlockSupport;
use PHPUnit\Framework\TestCase;

class NestedAttributesQueryServiceTest extends TestCase
{
    public function testBuildExactCustomFieldSearchQueryUsesTermOnly(): void
    {
        $blockSupport = new ResourceQueryBlockSupport();
        $subject = new NestedAttributesQueryService();
        $queryBlock = new QueryBlock('custom_field', 'remote-123');

        $clause = $subject->buildExactCustomFieldSearchQuery(
            $queryBlock,
            $blockSupport->buildFlatCustomMetadataExactTermClauses($queryBlock)
        );

        $encoded = json_encode($clause);

        $this->assertIsString($encoded);
        $this->assertStringContainsString('"term"', $encoded);
        $this->assertStringContainsString('attributes.value.raw', $encoded);
        $this->assertStringNotContainsString('query_string', $encoded);
        $this->assertStringNotContainsString('"match"', $encoded);
    }
}
