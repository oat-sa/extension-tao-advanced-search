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
use oat\generis\model\data\Ontology;
use oat\oatbox\log\LoggerService;
use oat\tao\model\resources\relation\ResourceRelation;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Index\Handler\ResourceUpdatedHandler;
use oat\taoAdvancedSearch\model\Index\Service\ResourceReferencesService;
use oat\taoAdvancedSearch\model\Index\Specification\ItemResourceSpecification;
use oat\taoItems\model\media\ItemMediaResolver;
use oat\taoMediaManager\model\relation\MediaRelation;
use oat\taoMediaManager\model\relation\MediaRelationCollection;
use oat\taoMediaManager\model\relation\repository\query\FindAllByTargetQuery;
use oat\taoMediaManager\model\relation\repository\rdf\RdfMediaRelationRepository;
use core_kernel_classes_Resource;
use taoQtiTest_models_classes_QtiTestService as QtiTestService;
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

        $this->resource
            ->method('getUri')
            ->willReturn('http://resource/id');

        $this->mediaRelationRepository = $this->createMock(
            RdfMediaRelationRepository::class
        );

        $this->sut = new ResourceReferencesService(
            $this->createMock(LoggerService::class),
            $this->qtiTestService,
            $this->mediaRelationRepository
        );

        $this->itemType = $this->createMock(core_kernel_classes_Class::class);
        $this->itemType
            ->method('getUri')
            ->willReturn(TaoOntology::CLASS_URI_ITEM);
        $this->itemType
            ->method('equals')
            ->willReturnCallback(function (core_kernel_classes_Resource $res) {
                return $res->getUri() == TaoOntology::CLASS_URI_ITEM;
            });

        $this->testType = $this->createMock(core_kernel_classes_Class::class);
        $this->testType
            ->method('getUri')
            ->willReturn(TaoOntology::CLASS_URI_TEST);
        $this->testType
            ->method('equals')
            ->willReturnCallback(function (core_kernel_classes_Resource $res) {
                return $res->getUri() == TaoOntology::CLASS_URI_TEST;
            });
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

        $collection = $this->createMock(
            MediaRelationCollection::class
        );
        $collection->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$relation1, $relation2]));

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

        $ontologyMock = $this->createMock(Ontology::class);
        $ontologyMock
            ->expects($this->atLeastOnce())
            ->method('getClass')
            ->with(TaoOntology::CLASS_URI_ITEM)
            ->willReturn($this->itemType);

        $this->resource
            ->method('getTypes')
            ->willReturn([$this->itemType]);

        $this->resource
            ->method('getModel')
            ->willReturn($ontologyMock);

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

        $ontologyMock = $this->createMock(Ontology::class);
        $ontologyMock
            ->expects($this->exactly(2))
            ->method('getClass')
            ->willReturnMap([
                [TaoOntology::CLASS_URI_ITEM, $this->itemType],
                [TaoOntology::CLASS_URI_TEST, $this->testType],
            ]);

        $this->resource
            ->method('getTypes')
            ->willReturn([$this->testType]);

        $this->resource
            ->method('getModel')
            ->willReturn($ontologyMock);

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

        $ontologyMock = $this->createMock(Ontology::class);
        $ontologyMock
            ->expects($this->exactly(2))
            ->method('getClass')
            ->willReturnMap([
                [TaoOntology::CLASS_URI_ITEM, $this->itemType],
                [TaoOntology::CLASS_URI_TEST, $this->testType],
            ]);

        $this->resource
            ->method('getTypes')
            ->willReturn([$this->testType]);

        $this->resource
            ->method('getModel')
            ->willReturn($ontologyMock);

        $this->assertEquals(
            [
                'http://resources/referenced/1',
                'http://resources/referenced/2',
            ],
            $this->sut->getReferences($this->resource)
        );
    }
}
