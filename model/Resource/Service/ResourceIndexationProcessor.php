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

namespace oat\taoAdvancedSearch\model\Resource\Service;

use core_kernel_classes_Resource;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\SearchInterface;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;
use Psr\Log\LoggerInterface;

/**
 * @todo Find a better name for this
 */
class ResourceIndexationProcessor implements IndexerInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var IndexDocumentBuilderInterface */
    private $indexDocumentBuilder;

    /** @var SearchInterface */
    private $searchService;

    public function __construct(

        LoggerInterface $logger,
        IndexDocumentBuilderInterface $indexDocumentBuilder,
        SearchInterface $searchService
    ) {
        $this->logger = $logger;
        $this->indexDocumentBuilder = $indexDocumentBuilder;
        $this->searchService = $searchService;
    }

    public function addIndex($resource): void
    {
        $this->logger->info("Hello from ResourceIndexationProcessor");

        // @todo UpdateResourceInIndex from TAO Core calls directly
        //       $this->getSearchProxy()->index(), we'll likely need changes in
        //       Core to call this instead (that task just creates the doc with
        //       the document builder and then passes it to the search proxy)

        try {

            $totalIndexed = $this->searchService->index(
                [
                    $this->getDocumentFrom($resource)
                ]
            );

            if ($totalIndexed < 1) {
                $this->logWarning($resource, $totalIndexed);
            }
        } catch (\Throwable $exception) {
            $this->logException($exception);
        }
    }


    private function getDocumentFrom(
        core_kernel_classes_Resource $resource
    ): IndexDocument {
        $document = $this->indexDocumentBuilder->createDocumentFromResource(
            $resource
        );

        // @todo Add additional properties if needed (items referenced etc)

        return $document;
    }

    private function logWarning(
        core_kernel_classes_Resource $resource,
        int $totalIndexed
    ): void {
        $this->logger->warning(
            sprintf(
                'Could not index resource %s (%s): totalIndexed=%d',
                $resource->getLabel(),
                $resource->getUri(),
                $totalIndexed
            )
        );
    }

    private function logException(
        core_kernel_classes_Resource $resource,
        \Throwable $exception
    ): void {
        $this->logger->error(
            sprintf(
                'Could not index resource %s (%s). Error: %s',
                $resource->getLabel(),
                $resource->getUri(),
                $exception->getMessage()
            )
        );
    }
}
