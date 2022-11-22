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
use oat\oatbox\event\Event;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\SearchInterface;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use Psr\Log\LoggerInterface;

abstract class AbstractEventHandler implements EventHandlerInterface
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var SearchInterface */
    protected $searchService;

    /** @var IndexDocumentBuilderInterface */
    protected $documentBuilder;

    /** @var string[] */
    protected $supportedEvents = [];

    public function __construct(
        LoggerInterface $logger,
        IndexDocumentBuilderInterface $indexDocumentBuilder,
        SearchInterface $searchService,
        array $supportedEvents
    ) {
        $this->logger = $logger;
        $this->documentBuilder = $indexDocumentBuilder;
        $this->searchService = $searchService;
        $this->supportedEvents = $supportedEvents;
    }

    /**
     * @throws UnsupportedEventException
     */
    final public function handle(Event $event): void
    {
        $this->assertIsSupportedEvent($event);

        try {
            $resource = $this->getResource($event);
            $this->doHandle($event, $resource);
        } catch (Throwable $exception) {
            $this->logException($resource ?? null, $exception);
        }
    }

    abstract protected function getResource(
        Event $event
    ): core_kernel_classes_Resource;

    protected abstract function doHandle(
        Event $event,
        core_kernel_classes_Resource $resource
    ): void;

    protected function logResourceNotIndexed(
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

    protected function logException(
        ?core_kernel_classes_Resource $resource,
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
    protected function assertIsSupportedEvent($event): void
    {
        foreach ($this->supportedEvents as $eventClass) {
            if ($event instanceof $eventClass) {
                return;
            }
        }

        throw new UnsupportedEventException(
            count($this->supportedEvents) == 1
                ? current($this->supportedEvents)
                : sprintf('one of [%s]', implode(', ', $this->supportedEvents))
        );
    }
}
