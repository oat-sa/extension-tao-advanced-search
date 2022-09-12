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

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine;

use oat\taoAdvancedSearch\model\SearchEngine\Query;
use PHPUnit\Framework\TestCase;

class QueryTest extends TestCase
{
    public function testGetters(): void
    {
        $query = (new Query('indexName'))
            ->setOffset(7)
            ->setLimit(777)
            ->addCondition('a:"b"');

        $this->assertSame('indexName', $query->getIndex());
        $this->assertSame(7, $query->getOffset());
        $this->assertSame(777, $query->getLimit());
        $this->assertSame('a:"b"', $query->getQueryString());
    }
}
