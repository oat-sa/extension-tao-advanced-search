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
use InvalidArgumentException;
use oat\generis\test\MockObject;
use oat\generis\test\TestCase;
use oat\tao\model\Lists\Business\Domain\Metadata;
use oat\tao\model\Lists\Business\Domain\MetadataCollection;
use oat\tao\model\Lists\Business\Service\GetClassMetadataValuesService;
use oat\taoAdvancedSearch\model\Metadata\Normalizer\MetadataNormalizer;

class MetadataNormalizerTest extends TestCase
{
    /** @var MetadataNormalizer */
    private $subject;

    /** @var core_kernel_classes_Class|MockObject */
    private $classMock;

    /** @var Metadata|MockObject */
    private $propertyMock;

    /** @var GetClassMetadataValuesService|MockObject */
    private $getClassMetadataValuesServiceMock;

    /** @var MetadataCollection|MockObject */
    private $metadataCollection;

    public function setUp(): void
    {
        $this->subject = new MetadataNormalizer();
        $this->classMock = $this->createMock(core_kernel_classes_Class::class);
        $this->propertyMock = $this->createMock(Metadata::class);

        $this->getClassMetadataValuesServiceMock = $this->createMock(GetClassMetadataValuesService::class);

        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    GetClassMetadataValuesService::class => $this->getClassMetadataValuesServiceMock
                ]
            )
        );
    }

    public function testNormalizeTakesOnlyClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->subject->normalize('string');
    }

    public function testNormalize(): void
    {
        $this->metadataCollection = new MetadataCollection(
            $this->propertyMock
        );

        $this->getClassMetadataValuesServiceMock
            ->method('getByClassExplicitly')
            ->willReturn($this->metadataCollection);

        $this->classMock
            ->expects($this->exactly(4))
            ->method('getUri')
            ->willReturnOnConsecutiveCalls(
                'exampleClassUri',
                'exampleClassUri',
                'exampleParentClassUri',
                'otherExample'
            );

        $this->classMock
            ->expects($this->once())
            ->method('getLabel')
            ->willReturn('example Label');

        $this->classMock
            ->expects($this->once())
            ->method('getParentClasses')
            ->willReturn([$this->classMock]);

        $this->propertyMock
            ->expects($this->once())
            ->method('getPropertyUri')
            ->willReturn('propertyUri');

        $this->propertyMock
            ->expects($this->once())
            ->method('getType')
            ->willReturn('typeExample');

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
                        'propertyUri' => 'propertyUri',
                        'propertyLabel' => 'property label',
                        'propertyType' => 'typeExample',
                        'propertyValues' => null,
                    ]
                ]
            ],
            $result->getData()
        );
    }
}
