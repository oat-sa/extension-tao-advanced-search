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
 * Copyright (c) 2022-2023 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\Index\Service;

use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use oat\generis\test\ServiceManagerMockTrait;
use oat\oatbox\service\ServiceManager;
use oat\tao\model\media\TaoMediaResolver;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilder;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Index\Service\AdvancedSearchIndexDocumentBuilder;
use oat\taoAdvancedSearch\model\Test\Normalizer\TestNormalizer;
use oat\taoDeliveryRdf\model\DeliveryAssemblyService;
use oat\taoMediaManager\model\relation\service\IdDiscoverService;
use oat\taoQtiItem\model\qti\Img;
use oat\taoQtiItem\model\qti\Item;
use oat\taoQtiItem\model\qti\parser\ElementReferencesExtractor;
use oat\taoQtiItem\model\qti\QtiObject;
use oat\taoQtiItem\model\qti\Service as QtiItemService;
use oat\taoQtiItem\model\qti\XInclude;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdvancedSearchIndexDocumentBuilderTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var AdvancedSearchIndexDocumentBuilder */
    private $sut;

    /** @var IdDiscoverService|MockObject */
    private $idDiscoverService;

    /** @var ElementReferencesExtractor|MockObject */
    private $elementReferencesExtractor;

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

    /** @var Item|MockObject */
    private $testNormalizer;

    public function setUp(): void
    {
        $this->document = $this->createMock(IndexDocument::class);
        $this->elementReferencesExtractor = $this->createMock(ElementReferencesExtractor::class);
        $this->idDiscoverService = $this->createMock(IdDiscoverService::class);
        $this->itemService = $this->createMock(QtiItemService::class);
        $this->parentBuilder = $this->createMock(IndexDocumentBuilder::class);
        $this->qtiItem = $this->createMock(Item::class);
        $this->resolver = $this->createMock(TaoMediaResolver::class);
        $this->testNormalizer = $this->createMock(TestNormalizer::class);

        $this->deliveryType = $this->mockRDFClass(TaoOntology::CLASS_URI_DELIVERY);
        $this->itemType = $this->mockRDFClass(TaoOntology::CLASS_URI_ITEM);
        $this->testType = $this->mockRDFClass(TaoOntology::CLASS_URI_TEST);
        $this->genericType = $this->mockRDFClass(TaoOntology::CLASS_URI_OBJECT);

        $this->sut = new AdvancedSearchIndexDocumentBuilder(
            $this->elementReferencesExtractor,
            $this->parentBuilder,
            $this->idDiscoverService,
            $this->testNormalizer,
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

    public function testCreateDocumentFromResourceItemWithNoQtiItem(): void
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
            ->willReturn(null);

        $this->elementReferencesExtractor
            ->expects($this->never())
            ->method('extract');

        $this->idDiscoverService
            ->expects($this->never())
            ->method('discover');

        $document = $this->sut->createDocumentFromResource($anItem);

        $this->assertEquals(
            [
                'type' => ['document type'],
                'asset_uris' => [],
            ],
            $document->getBody()
        );
    }

    public function testCreateDocumentFromResourceTest(): void
    {
        $aTest = $this->mockResource([TaoOntology::CLASS_URI_TEST]);

        $this->document
            ->method('getBody')
            ->willReturn([
                'type' => ['document type'],
            ]);

        $this->testNormalizer
            ->expects($this->once())
            ->method('normalize')
            ->with($aTest)
            ->willReturn($this->document);

        $document = $this->sut->createDocumentFromResource($aTest);

        $this->assertEquals(
            [
                'type' => ['document type'],
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

        $testProp = new \core_kernel_classes_Property(TaoOntology::CLASS_URI_TEST);
        $itemProp = new \core_kernel_classes_Property(TaoOntology::CLASS_URI_ITEM);
        $anItem
            ->method('getProperty')
            ->willReturnMap([
                ['http://www.tao.lu/Ontologies/TAOTest.rdf#TestTestModel', $testProp],
                ['http://www.tao.lu/Ontologies/TAOItem.rdf#ItemModel', $itemProp],
            ]);

        $anItem
            ->method('getOnePropertyValue')
            ->willReturnCallback(
                function (\core_kernel_classes_Property $res) use ($types) {
                    return in_array($res->getUri(), $types);
                }
            );

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
