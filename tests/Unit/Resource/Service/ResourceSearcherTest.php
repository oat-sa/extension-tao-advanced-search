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

namespace oat\taoAdvancedSearch\tests\Unit\Resource\Service;

use core_kernel_classes_Resource;
use oat\generis\test\TestCase;
use oat\search\ResultSet;
use oat\tao\model\task\migration\service\ResultFilter;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableResourceRepository;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceSearcher;
use PHPUnit\Framework\MockObject\MockObject;

class ResourceSearcherTest extends TestCase
{
    /** @var ResourceSearcher */
    private $subject;

    /** @var IndexableResourceRepository|MockObject */
    private $indexableResourceRepository;

    public function setUp(): void
    {
        $this->indexableResourceRepository = $this->createMock(IndexableResourceRepository::class);

        $this->subject = new ResourceSearcher();
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    IndexableResourceRepository::class => $this->indexableResourceRepository,
                ]
            )
        );
    }

    public function testAddIndex(): void
    {
        $resource = $this->createMock(core_kernel_classes_Resource::class);

        $this->indexableResourceRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn(new ResultSet([$resource], 1));

        $result = $this->subject->search(
            new ResultFilter(
                [
                    'classUri' => 'uri',
                    'start' => 0,
                    'end' => 10,
                ]
            )
        );

        $this->assertSame(1, $result->count());
        $this->assertEquals($resource, $result->current()->getResult());
    }
}
