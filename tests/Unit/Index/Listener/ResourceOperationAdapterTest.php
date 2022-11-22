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
 * Copyright (c) 2021-2022 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\Index\Listener;

use oat\generis\model\data\event\ResourceUpdated;
use oat\generis\test\TestCase;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Index\Listener\AgnosticEventListener;
use oat\taoAdvancedSearch\model\Index\Listener\ResourceOperationAdapter;
use oat\taoTests\models\event\TestUpdatedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use core_kernel_classes_Resource;

class ResourceOperationAdapterTest extends TestCase
{
    /** @var AgnosticEventListener|MockObject */
    private $listener;

    /** @var ResourceOperationAdapter */
    private $sut;

    public function setUp(): void
    {
        $this->listener = $this->createMock(AgnosticEventListener::class);
        $this->sut = new ResourceOperationAdapter($this->listener);
    }

    public function testDispatchTestUpdatedEvent(): void
    {
        $testType = $this->createMock(core_kernel_classes_Resource::class);
        $testType
            ->method('getUri')
            ->willReturn(TaoOntology::CLASS_URI_TEST);

        $testResource = $this->createMock(core_kernel_classes_Resource::class);
        $testResource
            ->method('getUri')
            ->willReturn('http://test/1');

        $testResource
            ->method('getTypes')
            ->willReturn([$testType]);

        $this->listener
            ->expects($this->once())
            ->method('listen')
            ->with(new TestUpdatedEvent('http://test/1'));

        $this->sut->handleAddIndex($testResource);
    }

    public function testDispatchResourceUpdatedEvent(): void
    {
        $itemType = $this->createMock(core_kernel_classes_Resource::class);
        $itemType
            ->method('getUri')
            ->willReturn(TaoOntology::CLASS_URI_ITEM);

        $itemResource = $this->createMock(core_kernel_classes_Resource::class);
        $itemResource
            ->method('getUri')
            ->willReturn('http://item/1');

        $itemResource
            ->method('getTypes')
            ->willReturn([$itemType]);

        $this->listener
            ->expects($this->once())
            ->method('listen')
            ->with(new ResourceUpdated($itemResource));

        $this->sut->handleAddIndex($itemResource);
    }
}
