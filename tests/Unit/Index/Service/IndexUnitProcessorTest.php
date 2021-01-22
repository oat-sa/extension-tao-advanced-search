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

namespace oat\taoAdvancedSearch\tests\Unit\Index\Service;

use InvalidArgumentException;
use oat\generis\test\TestCase;
use oat\tao\model\task\migration\ResultUnit;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;
use oat\taoAdvancedSearch\model\Index\Service\IndexUnitProcessor;
use PHPUnit\Framework\MockObject\MockObject;

class IndexUnitProcessorTest extends TestCase
{
    /** @var IndexerInterface|MockObject */
    private $indexer;

    /** @var IndexUnitProcessor */
    private $unitProcessor;

    public function setUp(): void
    {
        $this->indexer = $this->createMock(IndexerInterface::class);

        $this->unitProcessor = new IndexUnitProcessor();
        $this->unitProcessor->setIndexer($this->indexer);
    }

    public function testProcess(): void
    {
        $this->indexer
            ->expects($this->once())
            ->method('addIndex')
            ->with('something');

        $this->unitProcessor->process(new ResultUnit('something'));
    }

    public function testProcessWithoutIndexerThrowsError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Indexer must be provided');

        (new IndexUnitProcessor())->process(new ResultUnit('something'));
    }
}
