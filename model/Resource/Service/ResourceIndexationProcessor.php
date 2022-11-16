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
use oat\oatbox\service\ServiceManager;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\SearchInterface;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;
use oat\taoAdvancedSearch\model\Resource\Service\DocumentTransformation\TestTransformationStrategy;
use Psr\Log\LoggerInterface;
// @todo Add a dependency to taoQtiTest in composer.json
use taoQtiTest_models_classes_QtiTestService;
use Throwable;

/**
 * @todo Find a better name for this?
 *
 * @todo UpdateResourceInIndex from TAO Core calls directly
 *       $this->getSearchProxy()->index(), we'll likely need changes in Core to
 *       call this instead (that task just creates the doc with the document
 *       builder and then passes it to the search proxy)
 */
class ResourceIndexationProcessor implements IndexerInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var IndexDocumentBuilderInterface */
    private $indexDocumentBuilder;

    /** @var SearchInterface */
    private $searchService;

    /** @var DocumentTransformationStrategy[] */
    private $transformations = [];

    public function __construct(
        LoggerInterface $logger,
        IndexDocumentBuilderInterface $indexDocumentBuilder,
        SearchInterface $searchService
    ) {
        $this->logger = $logger;
        $this->indexDocumentBuilder = $indexDocumentBuilder;
        $this->searchService = $searchService;

        // @todo Pass them directly by IoD
        $this->transformations = [
            ServiceManager::getServiceManager()->getContainer()->get(
                TestTransformationStrategy::class
            )
        ];
    }

    public function addIndex($resource): void
    {
        try {
            $totalIndexed = $this->searchService->index(
                [
                    $this->getDocumentFor($resource)
                ]
            );

            if ($totalIndexed < 1) {
                $this->logWarning($resource, $totalIndexed);
            }
        } catch (Throwable $exception) {
            $this->logException($resource, $exception);
        }
    }

    private function getDocumentFor(
        core_kernel_classes_Resource $resource
    ): IndexDocument {
        $this->logger->debug(
            sprintf(
                'Preparing data to index, resource = %s',
                $resource->getUri()
            )
        );

        // Index Document Builder is from Core
        $document = $this->indexDocumentBuilder->createDocumentFromResource(
            $resource
        );

        foreach ($this->transformations as $strategy) {
            $this->logger->debug(
                sprintf('Applying transformation: %s', get_class($strategy))
            );

            $document = $strategy->transform($resource, $document);
        }

        return $document;
    }

    /*private function getService(string $id)
    {
        return ServiceManager::getServiceManager()->getContainer()->get($id);
    }

    private function getQtiTestService(): taoQtiTest_models_classes_QtiTestService
    {
        /**
         * @fixme use DI
         * /
        return ServiceManager::getServiceManager()->get(
            taoQtiTest_models_classes_QtiTestService::class
        );
    }*/

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
        Throwable $exception
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
