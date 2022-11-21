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

declare(strict_types = 1);

namespace oat\taoAdvancedSearch\tests\Unit\Index\Service;

use oat\oatbox\log\LoggerService;
use oat\tao\model\media\MediaAsset;
use oat\tao\model\search\SearchInterface;
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

    /** @var SearchInterface|MockObject */
    private $search;

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
        $this->qtiItem = $this->createMock(QtiItem::class);

        $this->itemMediaResolver = $this->createMock(ItemMediaResolver::class);
        $this->search = $this->createMock(SearchInterface::class);
        $this->resource = $this->createMock(core_kernel_classes_Resource::class);

        $this->qtiItemBodyMock = $this->createMock(ContainerItemBody::class);

        $this->sut = new ResourceReferencesService(
            $this->createMock(LoggerService::class),
            $this->qtiItemService,
            $this->qtiTestService,
            $this->itemMediaResolver
        );
    }

    public function testGetReferencesForItemAssets(): void
    {
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
                    case 'http://object1/data':
                        return $asset1;
                    case 'http://object2/data':
                        return $asset2;
                }

                $this->fail('Unexpected Object URI: ' . $data);
            });


        // @todo Complete this test: This is migrated from a previous one that
        //       tested a bigger service holding the logic that is now in this
        //       one
        $this->markTestIncomplete('Not fully implemented');

        /*$this->search
            ->expects($this->once())
            ->method('index')
            ->willReturnCallback(function (array $documents) {
                /** @var $documents IndexDocument[] * /

                $this->assertCount(1, $documents);
                $this->assertEquals(
                    'resource-type',
                    $documents[0]->getBody()['type']
                );
                $this->assertEquals(
                    [
                        'http://resources/referenced/1',
                        'http://resources/referenced/2',
                    ],
                    $documents[0]->getBody()['referenced_resources']
                );

                return 1;
            });*/


    }

    // @todo More tests ...
}
