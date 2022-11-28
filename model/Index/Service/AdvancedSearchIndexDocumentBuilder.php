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

namespace oat\taoAdvancedSearch\model\Index\Service;

use common_Exception;
use common_exception_InconsistentData;
use core_kernel_classes_Resource;
use oat\oatbox\service\ServiceManager;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\index\IndexService;
use oat\tao\model\TaoOntology;
use oat\taoItems\model\media\ItemMediaResolver;
use oat\taoMediaManager\model\relation\service\IdDiscoverService;
use oat\taoQtiItem\model\qti\Img;
use oat\taoQtiItem\model\qti\parser\ElementReferencesExtractor;
use oat\taoQtiItem\model\qti\QtiObject;
use oat\taoQtiItem\model\qti\Service as QtiItemService;
use oat\taoQtiItem\model\qti\XInclude;
use taoQtiTest_models_classes_QtiTestService as QtiTestService;
use taoQtiTest_models_classes_QtiTestServiceException;
use ReflectionProperty;
use Exception;

class AdvancedSearchIndexDocumentBuilder implements IndexDocumentBuilderInterface
{
    private const QTI_IDENTIFIER_KEY = 'qit_identifier';
    private const ASSETS_KEY = 'asset_uris';
    private const ITEMS_KEY = 'item_uris';

    private QtiTestService $qtiTestService;
    private ElementReferencesExtractor $itemElementReferencesExtractor;
    private QtiItemService $qtiItemService;
    private IndexService $indexService;
    private IdDiscoverService $idDiscoverService;

    public function __construct(
        QtiTestService $qtiTestService,
        ElementReferencesExtractor $itemElementReferencesExtractor,
        IndexService $indexService,
        IdDiscoverService $idDiscoverService,
        QtiItemService $qtiItemService = null
    ) {
        $this->qtiTestService = $qtiTestService;
        $this->itemElementReferencesExtractor = $itemElementReferencesExtractor;
        $this->indexService = $indexService;
        $this->idDiscoverService = $idDiscoverService;
        $this->qtiItemService = $qtiItemService ?? QtiItemService::singleton();
    }

    /**
     * @throws common_Exception
     * @throws common_exception_InconsistentData
     * @throws Exception
     */
    public function createDocumentFromResource(core_kernel_classes_Resource $resource): IndexDocument
    {
        $document = $this->getDocumentBuilder()->createDocumentFromResource($resource);

        $this->populateReferences($resource, $document);

        return $document;
    }

    public function createDocumentFromArray(array $resourceData = []): IndexDocument
    {
        return $this->getDocumentBuilder()->createDocumentFromArray($resourceData);
    }

    /**
     * @throws Exception
     */
    private function populateReferences(core_kernel_classes_Resource $resource, IndexDocument $document): void
    {
        $reflector = new ReflectionProperty($document, 'body');
        $reflector->setAccessible(true);

        $body = $document->getBody();

        if ($this->isA(TaoOntology::CLASS_URI_ITEM, $resource)) {
            $body[self::ASSETS_KEY] = $this->getResourceURIsForItemAssets($resource);
        }

        if ($this->isA(TaoOntology::CLASS_URI_TEST, $resource)) {
            $body[self::ITEMS_KEY] = $this->getResourceURIsForTestItems($resource);
            $body[self::QTI_IDENTIFIER_KEY] = $this->getIdentifier($resource);
        }

        $reflector->setValue($document, $body);
    }

    /**
     * @throws taoQtiTest_models_classes_QtiTestServiceException
     */
    private function getIdentifier(core_kernel_classes_Resource $resource): ?string
    {
        if ($this->isA(TaoOntology::CLASS_URI_TEST, $resource)) {
            $jsonData = json_decode($this->qtiTestService->getJsonTest($resource));

            if (isset($jsonData->identifier)) {
                return (string)$jsonData->identifier;
            }
        }

        return null;
    }

    private function getResourceURIsForItemAssets(
        core_kernel_classes_Resource $resource
    ): array {
        $resolver = $this->mediaResolver ?? $this->getResolverForResource($resource);
        if ($resolver === null) {
            return [];
        }

        $qtiItem = $this->qtiItemService->getDataItemByRdfItem($resource);

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
    ): ?ItemMediaResolver {
        if (!class_exists(ItemMediaResolver::class)) {
            return null;
        }

        return new ItemMediaResolver($resource, '');
    }

    /**
     * @throws Exception
     */
    private function getResourceURIsForTestItems(core_kernel_classes_Resource $resource): array
    {
        $itemURIs = [];

        foreach ($this->qtiTestService->getItems($resource) as $item) {
            assert($item instanceof core_kernel_classes_Resource);

            $itemURIs[$item->getUri()] = $item->getUri();
        }

        return array_values($itemURIs);
    }

    private function isA(string $type, core_kernel_classes_Resource $resource): bool
    {
        $rootClass = $resource->getClass($type);

        foreach ($resource->getTypes() as $type) {
            if ($type->equals($rootClass) || $type->isSubClassOf($rootClass)) {
                return true;
            }
        }

        return false;
    }

    private function getDocumentBuilder(): IndexDocumentBuilderInterface
    {
        //@TODO Check if we can add this in the IndexService::getDocumentBuilder method
        $service = $this->indexService->getDocumentBuilder();
        $service->setServiceLocator(ServiceManager::getServiceManager());

        return $service;
    }
}
