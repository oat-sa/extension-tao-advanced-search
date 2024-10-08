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

namespace oat\taoAdvancedSearch\tests\Unit\model\Metadata\Repository;

use core_kernel_classes_Class;
use oat\generis\test\ServiceManagerMockTrait;
use PHPUnit\Framework\TestCase;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriRepository;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassCachedRepository;
use PHPUnit\Framework\MockObject\MockObject;

class ClassUriRepositoryTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var ClassUriRepository */
    private $subject;

    /** @var IndexableClassCachedRepository|MockObject */
    private $indexableClassRepository;

    public function setUp(): void
    {
        $this->indexableClassRepository = $this->createMock(IndexableClassCachedRepository::class);
        $this->subject = $this->createMock(ClassUriRepository::class);
        $this->subject = new ClassUriRepository();
        $this->subject->setServiceLocator(
            $this->getServiceManagerMock(
                [
                    IndexableClassCachedRepository::class => $this->indexableClassRepository,
                ]
            )
        );
    }

    public function testFindAll(): void
    {
        $classMock = $this->createMock(core_kernel_classes_Class::class);
        $classMock->method('getUri')
            ->willReturn('classUri');

        $subClassMock = $this->createMock(core_kernel_classes_Class::class);
        $subClassMock->method('getUri')
            ->willReturn('subClassUri');

        $this->indexableClassRepository
            ->method('findAll')
            ->willReturn([$classMock]);

        $classMock->method('getSubClasses')
            ->willReturn([$subClassMock]);

        $result = $this->subject->findAll();

        $this->assertCount(2, $result);

        foreach ($result as $classUri) {
            $this->assertTrue(
                in_array(
                    $classUri,
                    [
                        'classUri',
                        'subClassUri',
                    ]
                )
            );
        }
    }
}
