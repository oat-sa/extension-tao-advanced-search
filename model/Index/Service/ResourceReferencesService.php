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

use core_kernel_classes_Resource;
use oat\oatbox\service\ServiceManager;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\TaoOntology;
use oat\taoMediaManager\model\relation\repository\rdf\RdfMediaRelationRepository;
use oat\taoMediaManager\model\relation\service\IdDiscoverService;
use oat\taoQtiItem\model\qti\Img;
use oat\taoQtiItem\model\qti\parser\ElementReferencesExtractor;
use oat\taoQtiItem\model\qti\QtiObject;
use oat\taoQtiItem\model\qti\Service as QtiItemService;
use oat\taoQtiItem\model\qti\XInclude;
use taoQtiTest_models_classes_QtiTestService as QtiTestService;
use Psr\Log\LoggerInterface;
use Exception;
use taoQtiTest_models_classes_QtiTestServiceException;

/**
 * Used by UpdateResourceInIndex and ResourceUpdatedHandler to get information
 * about RDF resources.
 *
 * @deprecated Temporary solution, this centralised service should be replaced
 */
class ResourceReferencesService
{
    public const IDENTIFIER_KEY = 'identifier';
    public const REFERENCES_KEY = 'referenced_resources';

    /** @var LoggerInterface */
    private $logger;

    /** @var QtiTestService */
    private $qtiTestService;

    /** @var ElementReferencesExtractor */
    private $itemElementReferencesExtractor;

    /** @var QtiItemService */
    private $qtiItemService;

    public function __construct(
        LoggerInterface $logger,
        QtiTestService $qtiTestService,
        ElementReferencesExtractor $itemElementReferencesExtractor = null
    ) {
        $this->logger = $logger;
        $this->qtiTestService = $qtiTestService;
        $this->itemElementReferencesExtractor = $itemElementReferencesExtractor;
        $this->qtiItemService = QtiItemService::singleton();
    }

    /**
     * As updates may be triggered from the ResourceWatcher, we provide a
     * method to check if the resource is from a known type that should
     * include the referenced_resources data.
     *
     * @param core_kernel_classes_Resource $resource
     * @return bool
     */
    public function hasSupportedType(
        core_kernel_classes_Resource $resource
    ): bool {
        if ($this->isA(TaoOntology::CLASS_URI_ITEM, $resource)) {
            return true;
        }

        if ($this->isA(TaoOntology::CLASS_URI_TEST, $resource)) {
            return true;
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function getBodyWithReferences(
        core_kernel_classes_Resource $resource,
        IndexDocument $document
    ): array {
        $body = $document->getBody();

        if ($this->hasSupportedType($resource)) {
            $body[self::REFERENCES_KEY] = $this->getReferences($resource);

            $id = $this->getIdentifier($resource);

            if (!empty($id)) {
                $body[self::IDENTIFIER_KEY] = $id;
            }
        }

        return $body;
    }

    /**
     * @return string[]
     * @throws Exception
     */
    public function getReferences(core_kernel_classes_Resource $resource): array
    {
        $mediaURIs = [];
        if ($this->isA(TaoOntology::CLASS_URI_ITEM, $resource)) {
            $mediaURIs = $this->getResourceURIsForItemAssets($resource);
        } elseif ($this->isA(TaoOntology::CLASS_URI_TEST, $resource)) {
            $mediaURIs = $this->getResourceURIsForTestItems($resource);
        }

        // Remove duplicates *and* reindex the array to have sequential offsets
        return array_values(array_unique($mediaURIs));
    }

    /**
     * @throws taoQtiTest_models_classes_QtiTestServiceException
     */
    private function getIdentifier(
        core_kernel_classes_Resource $resource
    ): ?string {
        if ($this->isA(TaoOntology::CLASS_URI_TEST, $resource)) {
            $jsonData = json_decode(
                $this->qtiTestService->getJsonTest($resource)
            );

            if (isset($jsonData->identifier)) {
                return (string) $jsonData->identifier;
            }
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function getResourceURIsForItemAssets(
        core_kernel_classes_Resource $resource
    ): array {
        $idDiscoverService = ServiceManager::getServiceManager()
            ->getContainer()->get(IdDiscoverService::class);

        /** @var IdDiscoverService $idDiscoverService */


        /** @var Service $itemService */
        $qtiItem = $this->qtiItemService->getDataItemByRdfItem($resource);

        $ret = array_merge(
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
        );

        return $idDiscoverService->discover($ret);
    }

    /**
     * @throws Exception
     */
    private function getResourceURIsForTestItems(
        core_kernel_classes_Resource $resource
    ): array {
        $itemURIs = [];

        foreach ($this->qtiTestService->getItems($resource) as $item) {
            assert($item instanceof core_kernel_classes_Resource);

            $itemURIs[] = $item->getUri();
        }

        return $itemURIs;
    }

    private function isA(
        string $type,
        core_kernel_classes_Resource $resource
    ): bool {
        $rootClass = $resource->getClass($type);

        foreach ($resource->getTypes() as $type) {
            if ($type->equals($rootClass) || $type->isSubClassOf($rootClass)) {
                return true;
            }
        }

        return false;
    }
}
