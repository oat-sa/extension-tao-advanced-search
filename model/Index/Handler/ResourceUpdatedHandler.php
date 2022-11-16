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

use core_kernel_classes_Resource;
use oat\generis\model\data\event\ResourceUpdated;
use oat\oatbox\event\Event;
use oat\oatbox\service\ServiceManager;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\SearchInterface;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use oat\taoAdvancedSearch\model\Resource\Service\DocumentTransformation\TestTransformationStrategy;
use Psr\Log\LoggerInterface;

class ResourceUpdatedHandler implements EventHandlerInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var SearchInterface */
    private $searchService;

    /** @var IndexDocumentBuilderInterface */
    private $indexDocumentBuilder;

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

        // @todo Remove "transformations" (merge them into the handlers)
        $this->transformations = [
            ServiceManager::getServiceManager()->getContainer()->get(
                TestTransformationStrategy::class
            )
        ];
    }

    /**
     * @throws UnsupportedEventException
     */
    public function handle(Event $event): void
    {
        $this->logger->info(self::class.' called');

        $this->assertIsResourceUpdatedEvent($event);

        $this->addIndex($event->getResource());


    }

    private function addIndex($resource): void
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

        // @todo Get rid of "transformations"
        foreach ($this->transformations as $strategy) {
            $this->logger->debug(
                sprintf('Applying transformation: %s', get_class($strategy))
            );

            $document = $strategy->transform($resource, $document);
        }

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

    /**
     * @throws UnsupportedEventException
     */
    private function assertIsResourceUpdatedEvent($event): void
    {
        if (!($event instanceof ResourceUpdated)) {
            throw new UnsupportedEventException(ResourceUpdated::class);
        }
    }


}
