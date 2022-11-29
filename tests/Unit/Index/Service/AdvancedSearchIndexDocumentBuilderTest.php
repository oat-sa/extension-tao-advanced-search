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
use oat\oatbox\service\ServiceManager;
use oat\tao\model\media\TaoMediaResolver;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilder;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\index\IndexService;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Index\Service\AdvancedSearchIndexDocumentBuilder;
use oat\taoDeliveryRdf\model\DeliveryAssemblyService;
use oat\taoMediaManager\model\relation\service\IdDiscoverService;
use oat\taoQtiItem\model\qti\Img;
use oat\taoQtiItem\model\qti\Item;
use oat\taoQtiItem\model\qti\parser\ElementReferencesExtractor;
use oat\taoQtiItem\model\qti\QtiObject;
use oat\taoQtiItem\model\qti\Service as QtiItemService;
use oat\taoQtiItem\model\qti\XInclude;
use taoQtiTest_models_classes_QtiTestService as QtiTestService;
use PHPUnit\Framework\MockObject\MockObject;
use oat\generis\test\TestCase;

class AdvancedSearchIndexDocumentBuilderTest extends TestCase
{
    /** @var AdvancedSearchIndexDocumentBuilder */
    private $sut;

    /** @var QtiTestService|MockObject */
    private $qtiTestService;

    /** @var IdDiscoverService|MockObject */
    private $idDiscoverService;

    /** @var ElementReferencesExtractor|MockObject */
    private $elementReferencesExtractor;

    /** @var IndexService|MockObject */
    private $indexService;

    /** @var IndexDocumentBuilderInterface|MockObject */
    private $parentBuilder;

    /** @var IndexDocument|MockObject */
    private $document;

    /** @var core_kernel_classes_Class|MockObject */
    private $itemType;

    /** @var core_kernel_classes_Class|MockObject */
    private $testType;

    /** @var core_kernel_classes_Class|MockObject */
    private $genericType;

    /** @var core_kernel_classes_Class|MockObject */
    private $deliveryType;

    /** @var Item|MockObject */
    private $qtiItem;

    public function setUp(): void
    {
        $this->document = $this->createMock(IndexDocument::class);
        $this->qtiTestService = $this->createMock(QtiTestService::class);
        $this->elementReferencesExtractor = $this->createMock(ElementReferencesExtractor::class);
        $this->idDiscoverService = $this->createMock(IdDiscoverService::class);
        $this->indexService = $this->createMock(IndexService::class);
        $this->itemService = $this->createMock(QtiItemService::class);
        $this->parentBuilder = $this->createMock(IndexDocumentBuilder::class);
        $this->qtiItem = $this->createMock(Item::class);
        $this->resolver = $this->createMock(TaoMediaResolver::class);

        $this->deliveryType = $this->mockRDFClass(TaoOntology::CLASS_URI_DELIVERY);
        $this->itemType = $this->mockRDFClass(TaoOntology::CLASS_URI_ITEM);
        $this->testType = $this->mockRDFClass(TaoOntology::CLASS_URI_TEST);
        $this->genericType = $this->mockRDFClass(TaoOntology::CLASS_URI_OBJECT);

        $this->indexService
            ->method('getDocumentBuilder')
            ->willReturn($this->parentBuilder);

        ServiceManager::setServiceManager($this->getServiceLocatorMock());

        $this->sut = new AdvancedSearchIndexDocumentBuilder(
            $this->qtiTestService,
            $this->elementReferencesExtractor,
            $this->indexService,
            $this->idDiscoverService,
            $this->itemService,
            $this->resolver
        );
    }

    public function testCreateDocumentFromResourceItem(): void
    {
        $anItem = $this->mockResource([TaoOntology::CLASS_URI_ITEM]);

        $this->document
            ->method('getBody')
            ->willReturn([
                'type' => ['document type'],
            ]);

        $this->parentBuilder
            ->expects($this->once())
            ->method('createDocumentFromResource')
            ->with($anItem)
            ->willReturn($this->document);

        $this->itemService
            ->expects($this->once())
            ->method('getDataItemByRdfItem')
            ->willReturn($this->qtiItem);

        $this->elementReferencesExtractor
            ->expects($this->exactly(3))
            ->method('extract')
            ->willReturnCallback(function (Item $i, string $c, string $a) {
                if ($i !== $this->qtiItem) {
                    $this->fail();
                }

                if (($c === XInclude::class) && ($a === 'href')) {
                    return ['reference://XInclude/href'];
                }

                if (($c === QtiObject::class) && ($a === 'data')) {
                    return ['reference://QtiObject/data'];
                }

                if (($c === Img::class) && ($a === 'src')) {
                    return ['reference://Img/src'];
                }

                $this->fail('Unexpected extract() call');
            });

        $this->idDiscoverService
            ->expects($this->once())
            ->method('discover')
            ->with(
                [
                    'reference://XInclude/href',
                    'reference://QtiObject/data',
                    'reference://Img/src',
                ]
            )
            ->willReturn(['asset://1', 'asset://2', 'asset://3']);

        $document = $this->sut->createDocumentFromResource($anItem);

        $this->assertEquals(
            [
                'type' => ['document type'],
                'asset_uris' => ['asset://1', 'asset://2', 'asset://3'],
            ],
            $document->getBody()
        );
    }

    public function testCreateDocumentFromResourceTest(): void
    {
        $item1 = $this->mockResource([TaoOntology::CLASS_URI_ITEM]);
        $item2 = $this->mockResource([TaoOntology::CLASS_URI_ITEM]);
        $aTest = $this->mockResource([TaoOntology::CLASS_URI_TEST]);

        $item1
            ->expects($this->exactly(2))
            ->method('getUri')
            ->willReturn('item://1');

        $item2
            ->expects($this->exactly(2))
            ->method('getUri')
            ->willReturn('item://2');

        $this->document
            ->method('getBody')
            ->willReturn([
                'type' => ['document type'],
            ]);

        $this->parentBuilder
            ->expects($this->once())
            ->method('createDocumentFromResource')
            ->with($aTest)
            ->willReturn($this->document);

        $this->itemService
            ->expects($this->never())
            ->method('getDataItemByRdfItem');

        $this->qtiTestService
            ->expects($this->once())
            ->method('getItems')
            ->with($aTest)
            ->willReturn([$item1, $item2]);

        $this->qtiTestService
            ->expects($this->once())
            ->method('getJsonTest')
            ->with($aTest)
            ->willReturn('{"identifier": "test_id"}');

        $document = $this->sut->createDocumentFromResource($aTest);
        $this->assertEquals(
            [
                'type' => ['document type'],
                'item_uris' => [
                    'item://1',
                    'item://2'
                ],
                'qit_identifier' => 'test_id'
            ],
            $document->getBody()
        );
    }

    public function testCreateDocumentFromResourceDelivery(): void
    {
        if (!class_exists(DeliveryAssemblyService::class)) {
            $this->markTestSkipped(
                sprintf(
                    '%s from taoDeliveryRdfis not available',
                    DeliveryAssemblyService::class
                )
            );
        }

        $delivery = $this->mockResource([TaoOntology::CLASS_URI_DELIVERY]);
        $delivery
            ->expects($this->once())
            ->method('getPropertyValues')
            ->willReturnCallback(function (\core_kernel_classes_Property $p) {
                if ($p->getUri() === DeliveryAssemblyService::PROPERTY_ORIGIN) {
                    return ['http://origin/test'];
                }

                $this->fail('Unexpected property requested: ' . $p->getUri());
            });

        $this->document
            ->method('getBody')
            ->willReturn([
                'type' => ['document type'],
            ]);

        $this->parentBuilder
            ->expects($this->once())
            ->method('createDocumentFromResource')
            ->with($delivery)
            ->willReturn($this->document);

        $this->itemService
            ->expects($this->never())
            ->method('getDataItemByRdfItem');

        $this->qtiTestService
            ->expects($this->never())
            ->method('getItems');

        $this->qtiTestService
            ->expects($this->never())
            ->method('getJsonTest');

        $document = $this->sut->createDocumentFromResource($delivery);
        $this->assertEquals(
            [
                'type' => ['document type'],
                'test_uri' => 'http://origin/test',
            ],
            $document->getBody()
        );
    }

    private function mockResource(array $types): MockObject
    {
        $anItem = $this->createMock(core_kernel_classes_Resource::class);
        $anItem
            ->expects($this->atMost(4))
            ->method('getTypes')
            ->willReturn(array_map([$this, 'mockRDFClass'], $types));

        $anItem
            ->method('getClass')
            ->willReturnMap([
                [TaoOntology::CLASS_URI_ITEM, $this->itemType],
                [TaoOntology::CLASS_URI_TEST, $this->testType],
                [TaoOntology::CLASS_URI_DELIVERY, $this->deliveryType],
                [TaoOntology::CLASS_URI_OBJECT, $this->genericType],
            ]);

        return $anItem;
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
