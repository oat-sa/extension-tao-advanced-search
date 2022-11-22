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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\Index\Service;

use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use oat\oatbox\log\LoggerService;
use oat\tao\model\resources\relation\ResourceRelation;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Index\Handler\ResourceUpdatedHandler;
use oat\taoAdvancedSearch\model\Index\Service\ResourceReferencesService;
use oat\taoItems\model\media\ItemMediaResolver;
use oat\taoMediaManager\model\relation\MediaRelation;
use oat\taoMediaManager\model\relation\MediaRelationCollection;
use oat\taoMediaManager\model\relation\repository\query\FindAllByTargetQuery;
use oat\taoMediaManager\model\relation\repository\rdf\RdfMediaRelationRepository;
use taoQtiTest_models_classes_QtiTestService as QtiTestService;
use ArrayIterator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ResourceReferenceServiceTest extends TestCase
{
    /** @var ResourceReferencesService */
    private $sut;

    /** @var core_kernel_classes_Resource|MockObject  */
    private $resource;

    /** @var core_kernel_classes_Resource|MockObject */
    private $item1;

    /** @var core_kernel_classes_Resource|MockObject */
    private $item2;

    /** @var QtiTestService|MockObject */
    private $qtiTestService;

    /** @var core_kernel_classes_Class|MockObject */
    private $itemType;

    /** @var core_kernel_classes_Class|MockObject */
    private $testType;

    /** @var core_kernel_classes_Class|MockObject */
    private $genericType;

    /** @var RdfMediaRelationRepository|MockObject */
    private $mediaRelationRepository;

    public function setUp(): void
    {
        if (!class_exists(ItemMediaResolver::class)) {
            $this->markTestSkipped(
                sprintf(
                    '%s needs %s from MediaManager',
                    ResourceUpdatedHandler::class,
                    ItemMediaResolver::class
                )
            );
        }

        $this->qtiTestService = $this->createMock(QtiTestService::class);
        $this->item1 = $this->createMock(core_kernel_classes_Resource::class);
        $this->item2 = $this->createMock(core_kernel_classes_Resource::class);
        $this->resource = $this->createMock(core_kernel_classes_Resource::class);

        $this->mediaRelationRepository = $this->createMock(
            RdfMediaRelationRepository::class
        );

        $this->sut = new ResourceReferencesService(
            $this->createMock(LoggerService::class),
            $this->qtiTestService,
            $this->mediaRelationRepository
        );

        $this->itemType = $this->mockRDFClass(TaoOntology::CLASS_URI_ITEM);
        $this->testType = $this->mockRDFClass(TaoOntology::CLASS_URI_TEST);
        $this->genericType = $this->mockRDFClass(TaoOntology::CLASS_URI_OBJECT);

        $this->resource
            ->method('getUri')
            ->willReturn('http://resource/id');

        $this->resource
            ->method('getClass')
            ->willReturnMap([
                [TaoOntology::CLASS_URI_ITEM, $this->itemType],
                [TaoOntology::CLASS_URI_TEST, $this->testType],
                [TaoOntology::CLASS_URI_OBJECT, $this->genericType],
            ]);
    }

    /**
     * @dataProvider hasSupportedTypeDataProvider
     */
    public function testHasSupportedType(
        bool $expected,
        core_kernel_classes_Resource $resource
    ): void {
        $resource
            ->method('getClass')
            ->willReturnMap([
                [TaoOntology::CLASS_URI_ITEM, $this->itemType],
                [TaoOntology::CLASS_URI_TEST, $this->testType],
                [TaoOntology::CLASS_URI_OBJECT, $this->genericType],
            ]);

        $this->assertEquals(
            $expected,
            $this->sut->hasSupportedType($resource)
        );
    }

    public function hasSupportedTypeDataProvider(): array
    {
        $anItem = $this->createMock(core_kernel_classes_Resource::class);
        $anItem
            ->expects($this->once())
            ->method('getTypes')
            ->willReturn([
                $this->mockRDFClass(TaoOntology::CLASS_URI_ITEM)
            ]);

        $aTest = $this->createMock(core_kernel_classes_Resource::class);
        $aTest
            ->expects($this->exactly(2))
            ->method('getTypes')
            ->willReturn([
                $this->mockRDFClass(TaoOntology::CLASS_URI_TEST)
            ]);

        $anObject = $this->createMock(core_kernel_classes_Resource::class);
        $anObject
            ->expects($this->exactly(2))
            ->method('getTypes')
            ->willReturn([
                $this->mockRDFClass(TaoOntology::CLASS_URI_OBJECT)
            ]);

        $itemSubclass = $this->mockRDFClass('http://ontology/item/subtype');
        $itemSubclass
            ->method('isSubClassOf')
            ->willReturnCallback(function (core_kernel_classes_Resource $res) {
                return $res->getUri() === TaoOntology::CLASS_URI_ITEM;
            });

        $aSubtype = $this->createMock(core_kernel_classes_Resource::class);
        $aSubtype
            ->expects($this->exactly(1))
            ->method('getTypes')
            ->willReturn([
                $itemSubclass
            ]);

        return [
            'Item type' => [true, $anItem],
            'Test type' => [true, $aTest],
            'Other type' => [false, $anObject],
            'Item subclass' => [true, $aSubtype],
        ];
    }

    public function testGetReferencesForItemAssets(): void
    {
        $relation1 = $this->createMock(ResourceRelation::class);
        $relation1
            ->expects($this->once())
            ->method('getSourceId')
            ->willReturn('http://taomedia/asset1');

        $relation2 = $this->createMock(ResourceRelation::class);
        $relation2
            ->expects($this->once())
            ->method('getSourceId')
            ->willReturn('http://taomedia/asset2');

        $collection = $this->createMock(MediaRelationCollection::class);
        $collection
            ->expects($this->once())
            ->method('getIterator')
            ->willReturn(
                new ArrayIterator([$relation1, $relation2])
            );

        $this->mediaRelationRepository
            ->expects($this->once())
            ->method('findAllByTarget')
            ->willReturnCallback(
                function (FindAllByTargetQuery $query) use ($collection) {
                    $this->assertEquals(
                        'http://resource/id',
                        $query->getTargetId()
                    );
                    $this->assertEquals(
                        MediaRelation::ITEM_TYPE,
                        $query->getType()
                    );

                    return $collection;
            });

        $this->qtiTestService
            ->expects($this->never())
            ->method('getItems');

        $this->resource
            ->method('getTypes')
            ->willReturn([$this->itemType]);

        $this->assertEquals(
            [
                'http://taomedia/asset1',
                'http://taomedia/asset2'
            ],
            $this->sut->getReferences($this->resource)
        );
    }

    public function testGetReferencesForTestItems(): void
    {
        $this->qtiTestService
            ->expects($this->once())
            ->method('getItems')
            ->with($this->resource)
            ->willReturn([$this->item1]);

        $this->item1
            ->expects($this->once())
            ->method('getUri')
            ->willReturn('http://resources/referenced/1');

        $this->resource
            ->method('getTypes')
            ->willReturn([$this->testType]);

        $this->assertEquals(
            [
                'http://resources/referenced/1',
            ],
            $this->sut->getReferences($this->resource)
        );
    }

    public function testTestItemReferencesAreUnique(): void
    {
        $this->qtiTestService
            ->expects($this->once())
            ->method('getItems')
            ->with($this->resource)
            ->willReturn([
                $this->item1,
                $this->item2,
                $this->item1,
            ]);

        $this->item1
            ->expects($this->exactly(2))
            ->method('getUri')
            ->willReturn('http://resources/referenced/1');

        $this->item2
            ->expects($this->once())
            ->method('getUri')
            ->willReturn('http://resources/referenced/2');

        $this->resource
            ->method('getTypes')
            ->willReturn([$this->testType]);

        $this->resource
            ->method('getClass')
            ->willReturnMap([
                [TaoOntology::CLASS_URI_ITEM, $this->itemType],
                [TaoOntology::CLASS_URI_TEST, $this->testType],
                [TaoOntology::CLASS_URI_OBJECT, $this->genericType],
            ]);

        $this->assertEquals(
            [
                'http://resources/referenced/1',
                'http://resources/referenced/2',
            ],
            $this->sut->getReferences($this->resource)
        );
    }

    public function getClassMethodMock($type): core_kernel_classes_Class
    {
        switch ($type) {
            case TaoOntology::CLASS_URI_ITEM:
                return $this->mockRDFClass(TaoOntology::CLASS_URI_ITEM);
            case TaoOntology::CLASS_URI_TEST:
                return $this->mockRDFClass(TaoOntology::CLASS_URI_TEST);
            case TaoOntology::CLASS_URI_OBJECT:
                return $this->mockRDFClass(TaoOntology::CLASS_URI_OBJECT);
        }

        $this->fail("Unexpected type requested: " . $type);
    }

    private function mockRDFClass(string $classUri): core_kernel_classes_Class
    {
        $type = $this->createMock(core_kernel_classes_Class::class);
        $type
            ->method('getUri')
            ->willReturn($classUri);
        $type
            ->method('equals')
            ->willReturnCallback(
                function (core_kernel_classes_Resource $res) use ($classUri) {
                    return $res->getUri() === $classUri;
                }
            );

        return $type;
    }
}
