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
use core_kernel_classes_Property;
use InvalidArgumentException;
use oat\generis\test\MockObject;
use oat\generis\test\TestCase;
use oat\taoAdvancedSearch\model\Metadata\Normalizer\MetadataNormalizer;

class MetadataNormalizerTest extends TestCase
{
    /** @var MetadataNormalizer */
    private $subject;

    /** @var core_kernel_classes_Class|MockObject */
    private $classMock;

    /** @var core_kernel_classes_Property|MockObject */
    private $propertyMock;

    public function setUp(): void
    {
        $this->subject = new MetadataNormalizer();
        $this->classMock = $this->createMock(core_kernel_classes_Class::class);
        $this->propertyMock = $this->createMock(core_kernel_classes_Property::class);
    }

    public function testNormalizeTakesOnlyClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->subject->normalize('string');
    }

    public function testNormalize(): void
    {
        $this->classMock
            ->expects($this->exactly(3))
            ->method('getUri')
            ->willReturnOnConsecutiveCalls(
                'exampleClassUri',
                'exampleClassUri',
                'exampleParentClassUri'
            );

        $this->classMock
            ->expects($this->once())
            ->method('getLabel')
            ->willReturn('example Label');

        $this->classMock
            ->expects($this->once())
            ->method('getProperties')
            ->willReturn([$this->propertyMock]);

        $this->classMock
            ->expects($this->once())
            ->method('getParentClasses')
            ->willReturn([$this->classMock]);

        $this->propertyMock
            ->expects($this->once())
            ->method('getUri')
            ->willReturn('propertyUriExample');

        $this->propertyMock
            ->expects($this->once())
            ->method('getLabel')
            ->willReturn('property label');

        $result = $this->subject->normalize($this->classMock);

        $this->assertEquals('example Label', $result->getLabel());
        $this->assertEquals('exampleClassUri', $result->getId());
        $this->assertEquals(
            [
                'type' => 'property-list',
                'parentClass' => 'exampleParentClassUri',
                'propertiesTree' => [
                    [
                        'propertyUri' => 'propertyUriExample',
                        'propertyLabel' => 'property label'
                    ]
                ]
            ],
            $result->getData()
        );
    }
}
