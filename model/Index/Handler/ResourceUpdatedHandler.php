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

namespace oat\taoAdvancedSearch\model\Index\Handler;

use common_Exception;
use common_exception_InconsistentData;
use core_kernel_classes_Resource;
use Exception;
use oat\generis\model\data\event\ResourceUpdated;
use oat\oatbox\event\Event;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\SearchInterface;
use oat\taoAdvancedSearch\model\Index\Specification\ItemResourceSpecification;
use oat\taoItems\model\media\ItemMediaResolver;
use oat\taoQtiItem\model\qti\interaction\MediaInteraction;
use oat\taoQtiItem\model\qti\Service as QtiService;
use Psr\Log\LoggerInterface;
use tao_helpers_Uri;
use Throwable;

class ResourceUpdatedHandler extends AbstractEventHandler
{
    /** @var ItemResourceSpecification */
    private $itemSpecification;

    /** @var QtiService */
    private $qtiService;

    public function __construct(
        LoggerInterface $logger,
        IndexDocumentBuilderInterface $indexDocumentBuilder,
        SearchInterface $searchService,
        ItemResourceSpecification $itemSpecification,
        QtiService $qtiService
    ) {
        parent::__construct(
            $logger,
            $indexDocumentBuilder,
            $searchService,
            [ResourceUpdated::class]
        );

        $this->itemSpecification = $itemSpecification;
        $this->qtiService = $qtiService;
    }

    protected function getResource(Event $event): core_kernel_classes_Resource
    {
        /** @var $event ResourceUpdated */
        return $event->getResource();
    }

    /**
     * @throws common_exception_InconsistentData
     * @throws common_Exception
     */
    public function doHandle(
        Event $event,
        core_kernel_classes_Resource $resource
    ): void {
        $doc = $this->getDocumentFor($resource);
        $totalIndexed = $this->searchService->index([$doc]);

        if ($totalIndexed < 1) {
            $this->logResourceNotIndexed($resource, $totalIndexed);
        }
    }

    /**
     * @throws common_exception_InconsistentData
     * @throws common_Exception
     */
    private function getDocumentFor(
        core_kernel_classes_Resource $resource
    ): IndexDocument {
        $this->logger->debug(
            sprintf(
                '%s: Preparing data to index, resource = %s',
                self::class,
                $resource->getUri()
            )
        );

        $document = $this->indexDocumentBuilder->createDocumentFromResource(
            $resource
        );

        return new IndexDocument(
            $document->getId(),
            $this->getBody($document, $resource),
            $document->getIndexProperties(),
            $document->getDynamicProperties(),
            $document->getAccessProperties()
        );
    }

    /**
     * @throws common_Exception
     */
    private function getBody(
        IndexDocument $document,
        core_kernel_classes_Resource $resource
    ): array {
        $references = [];

        if ($this->itemSpecification->isSatisfiedBy($resource)) {
            $references = $this->getResourceURIsFromItem($resource);
        }

        $body = $document->getBody();
        $body['referenced_resources'] = $references;

        return $body;
    }

    /**
     * @throws common_Exception
     */
    private function getResourceURIsFromItem(
        core_kernel_classes_Resource $resource
    ): array {
        $resolver = $this->getResolver($resource);
        if ($resolver === null) {
            return [];
        }

        $mediaURIs = [];
        $qtiItem = $this->qtiService->getDataItemByRdfItem($resource);

        foreach ($qtiItem->getBody()->getElements() as $element) {
            if ($element instanceof MediaInteraction) {
                try {
                    $mediaURIs[] = $this->extractMediaURI($resolver, $element);
                } catch (Throwable $e) {
                    $this->logger->warning('Unable to extract media URI');
                }
            }
        }

        // Remove duplicates *and* reindex the array to have sequential offsets
        return array_values(array_unique($mediaURIs));
    }

    private function getResolver(
        core_kernel_classes_Resource $resource
    ): ?ItemMediaResolver {
        if (!class_exists(ItemMediaResolver::class)) {
            $this->logger->debug('ItemMediaResolver not available');
            return null;
        }

        return new ItemMediaResolver($resource, '');
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
}
