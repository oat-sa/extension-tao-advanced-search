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

namespace oat\taoAdvancedSearch\tests\Unit\model\Metadata\Normalizer;

use core_kernel_classes_Class;
use oat\generis\test\TestCase;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Metadata\Factory\ClassPathFactory;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassCachedRepository;
use PHPUnit\Framework\MockObject\MockObject;

class ClassPathFactoryTest extends TestCase
{
    /** @var ClassPathFactory */
    private $subject;

    /** @var IndexableClassCachedRepository|MockObject */
    private $indexableClassRepository;

    public function setUp(): void
    {
        $this->subject = new ClassPathFactory();
        $this->indexableClassRepository = $this->createMock(IndexableClassCachedRepository::class);

        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    IndexableClassCachedRepository::class => $this->indexableClassRepository,
                ]
            )
        );
    }

    public function testCreate(): void
    {
        $class = $this->createMock(core_kernel_classes_Class::class);
        $parentClass = $this->createMock(core_kernel_classes_Class::class);

        $this->indexableClassRepository
            ->method('findAllUris')
            ->willReturn(
                [
                    TaoOntology::CLASS_URI_ITEM,
                ]
            );

        $class->method('getUri')
            ->willReturn('classUri');

        $parentClass->method('getUri')
            ->willReturn('parentClassUri');

        $class->expects($this->once())
            ->method('getParentClasses')
            ->willReturn([$parentClass]);

        $result = $this->subject->create($class);

        $this->assertEquals(
            [
                'classUri',
                'parentClassUri',
            ],
            $result
        );
    }
}
