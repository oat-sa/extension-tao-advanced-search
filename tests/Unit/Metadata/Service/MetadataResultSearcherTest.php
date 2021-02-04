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

namespace oat\taoAdvancedSearch\tests\Unit\model\Metadata\Service;

use core_kernel_classes_Class;
use oat\generis\model\data\Ontology;
use oat\generis\test\MockObject;
use oat\generis\test\TestCase;
use oat\tao\model\task\migration\ResultUnit;
use oat\tao\model\task\migration\service\ResultFilter;
use oat\taoAdvancedSearch\model\Metadata\Service\MetadataResultSearcher;

class MetadataResultSearcherTest extends TestCase
{
    /** @var MetadataResultSearcher */
    private $subject;

    /** @var ResultFilter|MockObject */
    private $filterMock;

    /** @var Ontology|MockObject */
    private $ontologyMock;

    /** @var core_kernel_classes_Class|MockObject */
    private $classMock;

    public function setUp(): void
    {
        $this->filterMock = $this->createMock(ResultFilter::class);
        $this->ontologyMock = $this->createMock(Ontology::class);
        $this->classMock = $this->createMock(core_kernel_classes_Class::class);

        $this->subject = new MetadataResultSearcher();

        $this->subject->setModel($this->ontologyMock);
    }

    public function testSearch(): void
    {
        $this->ontologyMock
            ->method('getClass')
            ->willReturn($this->classMock);

        $this->classMock
            ->method('getSubClasses')
            ->willReturn([$this->classMock]);

        $result = $this->subject->search($this->filterMock);
        $this->assertCount(8, $result);
        foreach ($result->getValues() as $result) {
            $this->assertInstanceOf(ResultUnit::class, $result);
        }
    }
}

