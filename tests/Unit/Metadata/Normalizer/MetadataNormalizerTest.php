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

    /** @var core_kernel_classes_Property|MockObject */
    private $propertyMock;

    /** @var GetClassMetadataValuesService|MockObject */
    private $getClassMetadataValuesServiceMock;

    /** @var Metadata|MockObject */
    private $metadataMock;

    public function setUp(): void
    {
        $this->subject = new MetadataNormalizer();
        $this->classMock = $this->createMock(core_kernel_classes_Class::class);
        $this->propertyMock = $this->createMock(core_kernel_classes_Property::class);
        $this->getClassMetadataValuesServiceMock = $this->createMock(GetClassMetadataValuesService::class);
        $this->metadataMock = $this->createMock(Metadata::class);

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

    /**
     * @dataProvider getDataProvider
     */
    public function testNormalize(
        string $classUri,
        int $getByClassExplicitlyCount,
        int $getByClassRecursiveCount,
        ?string $propertyUri,
        int $getValuesCount,
        ?array $getValuesResult
    ): void {
        $this->classMock
            ->expects($this->exactly(4))
            ->method('getUri')
            ->willReturnOnConsecutiveCalls(
                'exampleClassUri',
                'exampleClassUri',
                'exampleParentClassUri',
                $classUri
            );

        $this->classMock
            ->expects($this->once())
            ->method('getLabel')
            ->willReturn('example Label');

        $this->classMock
            ->expects($this->once())
            ->method('getParentClasses')
            ->willReturn([$this->classMock]);

        $this->metadataMock
            ->method('getPropertyUri')
            ->willReturn('PropertyUri Example');

        $this->metadataMock
            ->method('getLabel')
            ->willReturn('Label Example');

        $this->metadataMock
            ->method('getType')
            ->willReturn('Type Example');

        $this->metadataMock
            ->method('getUri')
            ->willReturn($propertyUri);

        $this->getClassMetadataValuesServiceMock
            ->expects($this->exactly($getByClassExplicitlyCount))
            ->method('getByClassExplicitly')
            ->willReturn(new MetadataCollection($this->metadataMock));

        $this->getClassMetadataValuesServiceMock
            ->expects($this->exactly($getByClassRecursiveCount))
            ->method('getByClassRecursive')
            ->willReturn(new MetadataCollection($this->metadataMock));

        $result = $this->subject->normalize($this->classMock);

        $this->assertEquals('example Label', $result->getLabel());
        $this->assertEquals('exampleClassUri', $result->getId());
        $this->assertEquals(
            [
                'type' => 'property-list',
                'parentClass' => 'exampleParentClassUri',
                'propertiesTree' => [
                    [
                        'propertyUri' => 'PropertyUri Example',
                        'propertyLabel' => 'Label Example',
                        'propertyType' => 'Type Example',
                        'propertyValues' => $getValuesResult
                    ]
                ]
            ],
            $result->getData()
        );
    }

    public function getDataProvider()
    {
        return [
            'notRootClass' => [
                'http://www.tao.lu/Ontologies/NotRootClass',
                1,
                0,
                'Uri Example',
                0,
                null
            ],
            'rootClass' => [
                'http://www.tao.lu/Ontologies/TAOItem.rdf#Item',
                0,
                1,
                null,
                1,
                null
            ]
        ];
    }
}
