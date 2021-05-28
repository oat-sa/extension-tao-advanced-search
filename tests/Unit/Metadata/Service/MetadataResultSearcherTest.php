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
use oat\generis\test\TestCase;
use oat\tao\model\task\migration\ResultUnit;
use oat\tao\model\task\migration\service\ResultFilter;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriCachedRepository;
use oat\taoAdvancedSearch\model\Metadata\Service\MetadataResultSearcher;
use PHPUnit\Framework\MockObject\MockObject;

class MetadataResultSearcherTest extends TestCase
{
    /** @var MetadataResultSearcher */
    private $subject;

    /** @var ResultFilter|MockObject */
    private $filterMock;

    /** @var core_kernel_classes_Class|MockObject */
    private $classMock;

    /** @var ClassUriCachedRepository|MockObject */
    private $classUriRepository;

    public function setUp(): void
    {
        $this->classMock = $this->createMock(core_kernel_classes_Class::class);
        $this->classUriRepository = $this->createMock(ClassUriCachedRepository::class);

        $this->subject = new MetadataResultSearcher();
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    ClassUriCachedRepository::class => $this->classUriRepository,
                ]
            )
        );
    }

    public function testSearch(): void
    {
        $this->classUriRepository
            ->method('findAll')
            ->willReturn(
                [
                    'classUri1',
                    'classUri2',
                    'classUri3',
                ]
            );

        $result = $this->subject->search(
            new ResultFilter(
                [
                    'start' => 1,
                    'end' => 3,
                ]
            )
        );

        $this->assertCount(2, $result);
        $this->assertInstanceOf(ResultUnit::class, $result[0]);
        $this->assertInstanceOf(ResultUnit::class, $result[1]);

        $this->assertSame('classUri2', $result[0]->getResult());
        $this->assertSame('classUri3', $result[1]->getResult());
    }
}
