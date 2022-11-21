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
use oat\generis\model\data\event\ResourceUpdated;
use oat\oatbox\event\Event;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\SearchInterface;
use oat\taoAdvancedSearch\model\Index\Service\ResourceReferencesService;
use Psr\Log\LoggerInterface;

class ResourceUpdatedHandler extends AbstractEventHandler
{
    /** @var ResourceReferencesService */
    private $referencesService;

    public function __construct(
        LoggerInterface $logger,
        IndexDocumentBuilderInterface $indexDocumentBuilder,
        SearchInterface $searchService,
        ResourceReferencesService $referencesService
    ) {
        parent::__construct(
            $logger,
            $indexDocumentBuilder,
            $searchService,
            [ResourceUpdated::class]
        );

        $this->referencesService = $referencesService;
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
    protected function doHandle(
        Event $event,
        core_kernel_classes_Resource $resource
    ): void {
        $totalIndexed = $this->searchService->index(
            [
                $this->getDocument($resource)
            ]
        );

        if ($totalIndexed < 1) {
            $this->logResourceNotIndexed($resource, $totalIndexed);
        }
    }

    /**
     * @throws common_exception_InconsistentData
     * @throws common_Exception
     */
    private function getDocument(
        core_kernel_classes_Resource $resource
    ): IndexDocument {
        $document = $this->documentBuilder->createDocumentFromResource($resource);

        return new IndexDocument(
            $document->getId(),
            $this->referencesService->getBodyWithReferences($resource, $document),
            $document->getIndexProperties(),
            $document->getDynamicProperties(),
            $document->getAccessProperties()
        );
    }
}
