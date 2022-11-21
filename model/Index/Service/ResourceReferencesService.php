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
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\TaoOntology;
use oat\taoItems\model\media\ItemMediaResolver;
use oat\taoQtiItem\model\qti\interaction\MediaInteraction;
use oat\taoQtiItem\model\qti\Service as QtiItemService;
use taoQtiTest_models_classes_QtiTestService as QtiTestService;
use Psr\Log\LoggerInterface;
use tao_helpers_Uri;
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

    /** @var QtiItemService */
    private $qtiItemService;

    /** @var QtiTestService */
    private $qtiTestService;

    /** @var ItemMediaResolver|null */
    private $mediaResolver;

    public function __construct(
        LoggerInterface $logger,
        QtiItemService $qtiItemService,
        QtiTestService $qtiTestService,
        ItemMediaResolver $mediaResolver = null
    ) {
        $this->logger = $logger;
        $this->qtiItemService = $qtiItemService;
        $this->qtiTestService = $qtiTestService;
        $this->mediaResolver = $mediaResolver;
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
        } else if ($this->isA(TaoOntology::CLASS_URI_TEST, $resource)) {
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
        $resolver = $this->mediaResolver ?? $this->getResolverForResource($resource);
        if ($resolver === null) {
            return [];
        }

        $mediaURIs = [];
        $qtiItem = $this->qtiItemService->getDataItemByRdfItem($resource);

        foreach ($qtiItem->getBody()->getElements() as $element) {
            if ($element instanceof MediaInteraction) {
                try {
                    $mediaURIs[] = $this->extractMediaURI($resolver, $element);
                } catch (Throwable $e) {
                    $this->logger->warning('Unable to extract media URI');
                }
            }
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

    /**
     * @throws Exception if the associated media URI is malformed
     */
    private function extractMediaURI(
        ItemMediaResolver $resolver,
        MediaInteraction $interaction
    ): ?string {
        $data = trim($interaction->getObject()->getAttributeValue('data'));
        if (empty($data)) {
            return null;
        }

        return tao_helpers_Uri::decode(
            $resolver->resolve($data)->getMediaIdentifier()
        );
    }

    private function getResolverForResource(
        core_kernel_classes_Resource $resource
    ): ?ItemMediaResolver {
        if (!class_exists(ItemMediaResolver::class)) {
            $this->logger->debug('ItemMediaResolver not available');
            return null;
        }

        return new ItemMediaResolver($resource, '');
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
