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

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine\Driver\Elasticsearch;

use oat\generis\model\DependencyInjection\ServiceOptionsInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ElasticSearchConfigTest extends TestCase
{
    /** @var ElasticSearchConfig */
    private $sut;

    /** @var ServiceOptionsInterface|MockObject */
    private $options;

    protected function setUp(): void
    {
        $this->options = $this->createMock(ServiceOptionsInterface::class);
        $this->sut = new ElasticSearchConfig($this->options);
    }

    public function testPrefixAll(): void
    {
        $this->options
            ->method('get')
            ->willReturnCallback(
                static function (string $class, string $option) {
                    if ($option === ElasticSearchConfig::OPTION_INDEX_PREFIX) {
                        return 'p';
                    }

                    if ($option === ElasticSearchConfig::OPTION_HOSTS) {
                        return ['hosts'];
                    }

                    return null;
                }
            );

        $this->assertSame('p', $this->sut->getIndexPrefix());
        $this->assertSame(['hosts'], $this->sut->getHosts());
    }
}
