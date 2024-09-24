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

use InvalidArgumentException;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfig;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfigFactory;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexPrefixerTest extends TestCase
{
    /** @var IndexPrefixer */
    private $sut;

    /** @var ElasticSearchConfigFactory|MockObject */
    private $configFactory;

    /** @var ElasticSearchConfig|MockObject */
    private $config;

    protected function setUp(): void
    {
        $this->configFactory = $this->createMock(ElasticSearchConfigFactory::class);
        $this->config = $this->createMock(ElasticSearchConfig::class);

        $this->configFactory
            ->method('getConfig')
            ->willReturn($this->config);
        $this->sut = new IndexPrefixer($this->configFactory);
    }

    public function testPrefixAll(): void
    {
        $this->config
            ->expects($this->any())
            ->method('getIndexPrefix')
            ->willReturn('p');

        $this->assertEquals(
            [
                'p-a1',
                'p-b',
                'p-c2'
            ],
            $this->sut->prefixAll(
                [
                    'a1',
                    'b',
                    'c2'
                ]
            )
        );
    }

    /**
     * @dataProvider validateDataProvider
     */
    public function testValidate(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sut->validate($value);
    }

    public function validateDataProvider(): array
    {
        return [
            'no camel Case allowed' => [
                'Abc123',
            ],
            'no space allowed' => [
                'ab c',
            ],
            'no special chars allowed' => [
                'abc123$',
            ],
        ];
    }
}
