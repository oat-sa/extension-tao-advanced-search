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

namespace oat\taoAdvancedSearch\model\Index\Service;

use common_Exception;
use common_exception_InconsistentData;
use core_kernel_classes_Property;
use core_kernel_classes_Resource;
use oat\oatbox\service\ServiceManager;
use oat\tao\model\media\TaoMediaResolver;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Test\Normalizer\TestNormalizer;
use oat\taoDeliveryRdf\model\DeliveryAssemblyService;
use oat\taoItems\model\media\ItemMediaResolver;
use oat\taoMediaManager\model\relation\service\IdDiscoverService;
use oat\taoQtiItem\model\qti\Img;
use oat\taoQtiItem\model\qti\parser\ElementReferencesExtractor;
use oat\taoQtiItem\model\qti\QtiObject;
use oat\taoQtiItem\model\qti\Service as QtiItemService;
use oat\taoQtiItem\model\qti\XInclude;
use Exception;

class AdvancedSearchIndexDocumentBuilder implements IndexDocumentBuilderInterface
{
    private const ASSETS_KEY = 'asset_uris';
    private const TEST_KEY = 'test_uri';
    private const PROPERTY_MARKING_TEST = 'http://www.tao.lu/Ontologies/TAOTest.rdf#TestTestModel';
    private const PROPERTY_MARKING_ITEM = 'http://www.tao.lu/Ontologies/TAOItem.rdf#ItemModel';

    private ElementReferencesExtractor $itemElementReferencesExtractor;
    private QtiItemService $qtiItemService;
    private IndexDocumentBuilderInterface $legacyDocumentBuilder;
    private IdDiscoverService $idDiscoverService;
    private ?TaoMediaResolver $itemMediaResolver;
    private TestNormalizer $testNormalizer;

    public function __construct(
        ElementReferencesExtractor $itemElementReferencesExtractor,
        IndexDocumentBuilderInterface $legacyDocumentBuilder,
        IdDiscoverService $idDiscoverService,
        TestNormalizer $testNormalizer,
        QtiItemService $qtiItemService = null,
        TaoMediaResolver $itemMediaResolver = null
    ) {
        $this->itemElementReferencesExtractor = $itemElementReferencesExtractor;
        $this->legacyDocumentBuilder = $legacyDocumentBuilder;
        $this->idDiscoverService = $idDiscoverService;
        $this->testNormalizer = $testNormalizer;
        $this->qtiItemService = $qtiItemService ?? QtiItemService::singleton();
        $this->itemMediaResolver = $itemMediaResolver;
    }

    /**
     * @throws common_Exception
     * @throws common_exception_InconsistentData
     * @throws Exception
     */
    public function createDocumentFromResource(core_kernel_classes_Resource $resource): IndexDocument
    {
        if ($this->isA(TaoOntology::CLASS_URI_TEST, $resource)) {
            return $this->testNormalizer->normalize($resource);
        }

        return $this->populateReferences(
            $resource,
            $this->legacyDocumentBuilder->createDocumentFromResource($resource)
        );
    }

    public function createDocumentFromArray(array $resourceData = []): IndexDocument
    {
        return $this->legacyDocumentBuilder->createDocumentFromArray($resourceData);
    }

    /**
     * @throws Exception
     */
    private function populateReferences(core_kernel_classes_Resource $resource, IndexDocument $document): IndexDocument
    {
        $body = $document->getBody();

        if ($this->isA(TaoOntology::CLASS_URI_ITEM, $resource)) {
            $body[self::ASSETS_KEY] = $this->getResourceURIsForItemAssets($resource);
        }

        if ($this->isA(TaoOntology::CLASS_URI_DELIVERY, $resource)) {
            $body[self::TEST_KEY] = $this->getDeliveryTestId($resource);
        }

        return new IndexDocument(
            $document->getId(),
            $body,
            $document->getIndexProperties(),
            $document->getDynamicProperties(),
            $document->getAccessProperties()
        );
    }

    private function getDeliveryTestId(core_kernel_classes_Resource $resource): ?string
    {
        $values = $resource->getPropertyValues(
            new core_kernel_classes_Property(
                DeliveryAssemblyService::PROPERTY_ORIGIN
            )
        );

        $value = current($values);

        return $value ? (string) $value : null;
    }

    private function getResourceURIsForItemAssets(
        core_kernel_classes_Resource $resource
    ): array {
        $resolver = $this->mediaResolver ?? $this->getResolverForResource($resource);
        if ($resolver === null) {
            return [];
        }

        $qtiItem = $this->qtiItemService->getDataItemByRdfItem($resource);

        if ($qtiItem === null) {
            return [];
        }

        return $this->idDiscoverService->discover(
            array_merge(
                $this->itemElementReferencesExtractor->extract(
                    $qtiItem,
                    XInclude::class,
                    'href'
                ),
                $this->itemElementReferencesExtractor->extract(
                    $qtiItem,
                    QtiObject::class,
                    'data'
                ),
                $this->itemElementReferencesExtractor->extract(
                    $qtiItem,
                    Img::class,
                    'src'
                )
            )
        );
    }

    private function getResolverForResource(
        core_kernel_classes_Resource $resource
    ): ?TaoMediaResolver {
        if ($this->itemMediaResolver instanceof TaoMediaResolver) {
            return $this->itemMediaResolver;
        }

        if (!class_exists(ItemMediaResolver::class)) {
            return null;
        }

        return new ItemMediaResolver($resource, '');
    }

    private function isA(string $type, core_kernel_classes_Resource $resource): bool
    {
        if ($type === TaoOntology::CLASS_URI_TEST && $this->isTest($resource)) {
            return true;
        }

        if ($type === TaoOntology::CLASS_URI_ITEM && $this->isItem($resource)) {
            return true;
        }

        $rootClass = $resource->getClass($type);

        foreach ($resource->getTypes() as $type) {
            if ($type->equals($rootClass) || $type->isSubClassOf($rootClass)) {
                return true;
            }
        }

        return false;
    }

    private function isTest(core_kernel_classes_Resource $resource): bool
    {
        if ($resource->getOnePropertyValue($resource->getProperty(
            self::PROPERTY_MARKING_TEST
        ))) {
            return true;
        }

        return false;
    }

    private function isItem(core_kernel_classes_Resource $resource): bool
    {
        if ($resource->getOnePropertyValue($resource->getProperty(
            self::PROPERTY_MARKING_ITEM
        ))) {
            return true;
        }

        return false;
    }
}
