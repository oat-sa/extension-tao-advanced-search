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

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine\Service;

use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfig;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexPrefixerTest extends TestCase
{
    /** @var IndexPrefixer */
    private $sut;

    /** @var ElasticSearchConfig|MockObject */
    private $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ElasticSearchConfig::class);
        $this->sut = new IndexPrefixer($this->config);
    }

    public function testPrefixAll(): void
    {
        $this->config
            ->expects($this->any())
            ->method('getIndexPrefix')
            ->willReturn('p');

        $this->assertEquals(
            [
                'p_a',
                'p_b',
                'p_c'
            ],
            $this->sut->prefixAll(
                [
                    'a',
                    'b',
                    'c'
                ]
            )
        );
    }
}
