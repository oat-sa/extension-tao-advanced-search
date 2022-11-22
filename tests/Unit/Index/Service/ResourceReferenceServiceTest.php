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
use oat\tao\model\media\MediaAsset;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Index\Handler\ResourceUpdatedHandler;
use oat\taoAdvancedSearch\model\Index\Service\ResourceReferencesService;
use oat\taoAdvancedSearch\model\Index\Specification\ItemResourceSpecification;
use oat\taoItems\model\media\ItemMediaResolver;
use oat\taoQtiItem\model\qti\container\ContainerItemBody;
use oat\taoQtiItem\model\qti\interaction\MediaInteraction;
use oat\taoQtiItem\model\qti\interaction\ObjectInteraction;
use oat\taoQtiItem\model\qti\Item as QtiItem;
use oat\taoQtiItem\model\qti\Service as QtiItemService;
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

    /** @var QtiService|MockObject */
    private $qtiItemService;

    /** @var QtiTestService|MockObject */
    private $qtiTestService;

    /** @var QtiItem|MockObject */
    private $qtiItem;

    /** @var ContainerItemBody|MockObject */
    private $qtiItemBodyMock;

    /** @var ItemMediaResolver|MockObject */
    private $itemMediaResolver;

    /** @var core_kernel_classes_Class|MockObject */
    private $itemType;

    /** @var core_kernel_classes_Class|MockObject */
    private $testType;


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

        $this->qtiItemService = $this->createMock(QtiItemService::class);
        $this->qtiTestService = $this->createMock(QtiTestService::class);
        $this->itemMediaResolver = $this->createMock(ItemMediaResolver::class);

        $this->item1 = $this->createMock(core_kernel_classes_Resource::class);
        $this->item2 = $this->createMock(core_kernel_classes_Resource::class);
        $this->resource = $this->createMock(core_kernel_classes_Resource::class);
        $this->qtiItem = $this->createMock(QtiItem::class);

        $this->qtiItemBodyMock = $this->createMock(ContainerItemBody::class);

        $this->sut = new ResourceReferencesService(
            $this->createMock(LoggerService::class),
            $this->qtiItemService,
            $this->qtiTestService,
            $this->itemMediaResolver
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
        $this->qtiTestService
            ->expects($this->never())
            ->method('getItems');

        $this->qtiItemService
            ->expects($this->once())
            ->method('getDataItemByRdfItem')
            ->with($this->resource)
            ->willReturn($this->qtiItem);

        $this->qtiItem
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($this->qtiItemBodyMock);

        $object1 = $this->createMock(ObjectInteraction::class);
        $object1
            ->expects($this->once())
            ->method('getAttributeValue')
            ->willReturnMap([
                ['data', 'http://resources/referenced/1']
            ]);

        $object2 = $this->createMock(ObjectInteraction::class);
        $object2
            ->expects($this->once())
            ->method('getAttributeValue')
            ->willReturnMap([
                ['data', 'http://resources/referenced/2']
            ]);

        $interaction1 = $this->createMock(MediaInteraction::class);
        $interaction1
            ->expects($this->once())
            ->method('getObject')
            ->willReturn($object1);

        $interaction2 = $this->createMock(MediaInteraction::class);
        $interaction2
            ->expects($this->once())
            ->method('getObject')
            ->willReturn($object2);

        $this->qtiItemBodyMock
            ->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $interaction1,
                $interaction2
            ]);

        $asset1 = $this->createMock(MediaAsset::class);
        $asset1
            ->method('getMediaIdentifier')
            ->willReturn('http://taomedia/asset1');

        $asset2 = $this->createMock(MediaAsset::class);
        $asset2
            ->method('getMediaIdentifier')
            ->willReturn('http://taomedia/asset2');

        $this->itemMediaResolver
            ->method('resolve')
            ->willReturnCallback(function ($data) use ($asset1, $asset2) {
                switch ($data) {
                    case 'http://resources/referenced/1':
                        return $asset1;
                    case 'http://resources/referenced/2':
                        return $asset2;
                }

                $this->fail('Unexpected Object URI: ' . $data);
            });

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
        $this->qtiItemService
            ->expects($this->never())
            ->method('getDataItemByRdfItem');

        $this->qtiTestService
            ->expects($this->once())
            ->method('getItems')
            ->with($this->resource)
            ->willReturn([$this->item1]);

        $this->item1
            ->expects($this->once())
            ->method('getUri')
            ->willReturn('http://resources/referenced/1');

        $this->qtiItem
            ->expects($this->never())
            ->method('getBody');

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
        $this->qtiItemService
            ->expects($this->never())
            ->method('getDataItemByRdfItem');

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

        $this->qtiItem
            ->expects($this->never())
            ->method('getBody');

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
