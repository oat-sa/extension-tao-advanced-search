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
use oat\generis\model\data\Ontology;
use oat\generis\test\ServiceManagerMockTrait;
use PHPUnit\Framework\TestCase;
use oat\tao\model\Lists\Business\Domain\Metadata;
use oat\tao\model\Lists\Business\Domain\MetadataCollection;
use oat\tao\model\Lists\Business\Service\GetClassMetadataValuesService;
use oat\tao\model\search\index\DocumentBuilder\PropertyIndexReferenceFactory;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Metadata\Factory\ClassPathFactory;
use oat\taoAdvancedSearch\model\Metadata\Normalizer\MetadataNormalizer;
use oat\taoAdvancedSearch\model\Metadata\Specification\PropertyAllowedSpecification;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassCachedRepository;
use PHPUnit\Framework\MockObject\MockObject;

class MetadataNormalizerTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var MetadataNormalizer */
    private $subject;

    /** @var core_kernel_classes_Class|MockObject */
    private $classMock;

    /** @var GetClassMetadataValuesService|MockObject */
    private $getClassMetadataValuesServiceMock;

    /** @var Metadata|MockObject */
    private $metadataMock;

    /** @var Ontology|MockObject */
    private $ontology;

    /** @var ClassPathFactory|MockObject */
    private $classPathFactory;

    /** @var IndexableClassCachedRepository|MockObject */
    private $indexableClassRepository;

    /** @var PropertyAllowedSpecification|MockObject */
    private $propertyAllowedSpecification;

    /** @var PropertyIndexReferenceFactory|MockObject */
    private $propertyIndexReferenceFactory;

    public function setUp(): void
    {
        $this->subject = new MetadataNormalizer();
        $this->indexableClassRepository = $this->createMock(IndexableClassCachedRepository::class);
        $this->classMock = $this->createMock(core_kernel_classes_Class::class);
        $this->getClassMetadataValuesServiceMock = $this->createMock(GetClassMetadataValuesService::class);
        $this->metadataMock = $this->createMock(Metadata::class);
        $this->ontology = $this->createMock(Ontology::class);
        $this->classPathFactory = $this->createMock(ClassPathFactory::class);
        $this->propertyAllowedSpecification = $this->createMock(PropertyAllowedSpecification::class);
        $this->propertyIndexReferenceFactory = $this->createMock(PropertyIndexReferenceFactory::class);

        $this->classPathFactory
            ->method('create')
            ->willReturn([]);

        $this->subject->setServiceLocator(
            $this->getServiceManagerMock(
                [
                    GetClassMetadataValuesService::class => $this->getClassMetadataValuesServiceMock,
                    Ontology::SERVICE_ID => $this->ontology,
                    ClassPathFactory::class => $this->classPathFactory,
                    IndexableClassCachedRepository::class => $this->indexableClassRepository,
                    PropertyAllowedSpecification::class => $this->propertyAllowedSpecification,
                    PropertyIndexReferenceFactory::class => $this->propertyIndexReferenceFactory,
                ]
            )
        );
    }

    public function testNormalizeTakesOnlyClass(): void
    {
        $this->ontology
            ->method('getClass')
            ->willReturn($this->classMock);

        $this->propertyAllowedSpecification
            ->method('isSatisfiedBy')
            ->willReturn(true);

        $this->classMock
            ->method('isClass')
            ->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->subject->normalize('string');
    }

    /**
     * @dataProvider getDataProvider
     */
    public function testNormalize(
        string  $classUri,
        int     $getByClassExplicitlyCount,
        int     $getByClassRecursiveCount,
        ?string $propertyUri,
        ?array  $getValuesResult
    ): void
    {
        $this->ontology
            ->method('getProperty')
            ->willReturn($this->createMock(core_kernel_classes_Property::class));

        $this->propertyIndexReferenceFactory
            ->method('create')
            ->willReturn('Combobox_');

        $this->propertyIndexReferenceFactory
            ->method('createRaw')
            ->willReturn('Combobox_');


        $this->indexableClassRepository
            ->method('findAllUris')
            ->willReturn(
                [
                    TaoOntology::CLASS_URI_ITEM,
                ]
            );

        $this->propertyAllowedSpecification
            ->method('isSatisfiedBy')
            ->willReturn(true);

        $this->ontology
            ->method('getClass')
            ->willReturn($this->classMock);

        $this->classMock
            ->method('isClass')
            ->willReturn(true);

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
            ->method('getAlias')
            ->willReturn('Alias Example');

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

        $result = $this->subject->normalize($classUri);

        $this->assertEquals('example Label', $result->getLabel());
        $this->assertEquals('exampleClassUri', $result->getId());
        $this->assertEquals(
            [
                'type' => 'property-list',
                'parentClass' => 'exampleParentClassUri',
                'classPath' => [],
                'propertiesTree' => [
                    [
                        'propertyReference' => 'Combobox_',
                        'propertyRawReference' => 'Combobox_',
                        'propertyUri' => 'PropertyUri Example',
                        'propertyLabel' => 'Label Example',
                        'propertyAlias' => 'Alias Example',
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
                null
            ],
            'rootClass' => [
                TaoOntology::CLASS_URI_ITEM,
                0,
                1,
                null,
                null
            ]
        ];
    }
}
