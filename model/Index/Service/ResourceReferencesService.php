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
use oat\tao\model\resources\relation\ResourceRelation;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\TaoOntology;
use oat\taoMediaManager\model\relation\MediaRelation;
use oat\taoMediaManager\model\relation\repository\query\FindAllByTargetQuery;
use oat\taoMediaManager\model\relation\repository\rdf\RdfMediaRelationRepository;
use taoQtiTest_models_classes_QtiTestService as QtiTestService;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * Used by UpdateResourceInIndex and ResourceUpdatedHandler to get information
 * about resources across RDF resources.
 */
class ResourceReferencesService
{
    public const REFERENCES_KEY = 'referenced_resources';

    /** @var LoggerInterface */
    private $logger;

    /** @var QtiTestService */
    private $qtiTestService;

    /** @var RdfMediaRelationRepository|null */
    private $mediaRelationRepository;

    public function __construct(
        LoggerInterface $logger,
        QtiTestService $qtiTestService,
        RdfMediaRelationRepository $mediaRelationRepository = null
    ) {
        $this->logger = $logger;
        $this->qtiTestService = $qtiTestService;
        $this->mediaRelationRepository = $mediaRelationRepository;
    }

    /**
     * As updates may be triggered from the ResourceWatcher, we provide a
     * method to check if the resource is from a known type that should
     * include the referenced_resources data.
     *
     * @param core_kernel_classes_Resource $resource
     * @return bool
     */
    public function isSupportedType(
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

        if ($this->isSupportedType($resource)) {
            $body[self::REFERENCES_KEY] = $this->getReferences($resource);
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
     * @throws Exception
     */
    private function getResourceURIsForItemAssets(
        core_kernel_classes_Resource $resource
    ): array {
        if ($this->mediaRelationRepository === null) {
            $this->logger->warning('MediaRelationRepository not available');
            return [];
        }

        $mediaRelations = $this->mediaRelationRepository->findAllByTarget(
            new FindAllByTargetQuery(
                $resource->getUri(),
                MediaRelation::ITEM_TYPE
            )
        );

        $mediaURIs = [];

        foreach ($mediaRelations as $mediaRelation) {
            assert($mediaRelation instanceof ResourceRelation);
            $mediaURIs[] = $mediaRelation->getSourceId();
        }

        return $mediaURIs;
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
        $rootClass = $resource->getModel()->getClass($type);

        foreach ($resource->getTypes() as $type) {
            if ($type->equals($rootClass) || $type->isSubClassOf($rootClass)) {
                return true;
            }
        }

        return false;
    }
}
