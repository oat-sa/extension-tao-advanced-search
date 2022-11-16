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

namespace oat\taoAdvancedSearch\model\Index\Listener;

use core_kernel_classes_Resource;
use oat\generis\model\data\event\ResourceDeleted;
use oat\generis\model\data\event\ResourceUpdated;
use oat\oatbox\service\ServiceManager;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexationProcessor;
use oat\taoTests\models\event\TestUpdatedEvent;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use common_Logger;

class AgnosticEventListener implements ListenerInterface
{
    /**
     * Additional name this service is registered as.
     *
     * This is used to prevent the EventManager from trying to call listen()
     * statically (and crash).
     */
    //public const SERVICE_ID = 'taoAdvancedSearch/AgnosticEventListener';

    /** @var ResourceIndexationProcessor */
    private $processor;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        HandlerMap $handlerMap,

        // @todo These two will likely be removed and used only from "handlers"
        ResourceIndexationProcessor $processor,
        LoggerInterface $logger
    ) {
        // @todo ...

        // @todo Save a ref to handler map or copy its data here
        $this->processor = $processor;
        $this->logger = $logger;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws UnsupportedEventException
     */
    public function listen($event): void
    {
        common_Logger::singleton()->getLogger()->warning(
            "this is ".get_class($this)
        );

        // @todo Move handling implementations to particular handler services
        //       and pass them to this listener through DI
        if ($event instanceof ResourceUpdated) {
            $this->handleResourceUpdatedEvent($event);
        } else if ($event instanceof ResourceDeleted) {
            $this->handleResourceDeletedEvent($event);
        } else if ($event instanceof TestUpdatedEvent) {
            $this->handleTestUpdatedEvent($event);
        } else {
            $this->throwUnsupportedEvent();
        }
    }

    private function throwUnsupportedEvent()
    {
        throw new UnsupportedEventException(
            sprintf('one of [%s]',
                implode(
                    ', ',
                    [
                        ResourceUpdated::class,
                        ResourceDeleted::class,
                        TestUpdatedEvent::class
                    ]
                )
            )
        );
    }

    /**
     * @throws UnsupportedEventException
     */
    private function handleResourceDeletedEvent(ResourceDeleted $event): void
    {
        $container = ServiceManager::getServiceManager()->getContainer();
        \common_Logger::singleton()->logInfo(
            self::class.'::handleResourceDeletedEvent called'
        );

        $this->assertIsResourceDeletedEvent($event);

        // @todo Reomve the resource URI from all resources in index holding a
        //       reference to its URI
        // $this->resourceIndexer->addIndex(...);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws UnsupportedEventException
     */
    private function handleResourceUpdatedEvent(ResourceUpdated $event): void
    {
        \common_Logger::singleton()->logInfo(
            self::class.'::handleResourceUpdatedEvent called'
        );

        common_Logger::singleton()->getLogger()->warning(
            "this is ".get_class($this));

        $this->assertIsResourceUpdatedEvent($event);

        // Please note this is in fact called statically
        // (i.e. $this is not available)

        try {
            $this->getProcessor()->addIndex($event->getResource());
        } catch (Throwable $e) {
            common_Logger::singleton()->getLogger()->warning(
                sprintf('Exception on %s: %s', self::class, $e->getMessage())
            );
        }
    }

    private function handleTestUpdatedEvent(TestUpdatedEvent $event): void
    {
        \common_Logger::singleton()->logInfo(
            self::class.'::handleTestUpdatedEvent called'
        );

        common_Logger::singleton()->getLogger()->warning(
            "this is ".get_class($this)
        );

        $this->assertIsTestUpdatedEvent($event);

        // Please note this is in fact called statically
        // (i.e. $this is not available)

        try {
            $container = ServiceManager::getServiceManager()->getContainer();
            $processor = $container->get(ResourceIndexationProcessor::class);

            $processor->addIndex(self::getResourceForEvent($event));
        } catch (Throwable $e) {
            common_Logger::singleton()->getLogger()->warning(
                sprintf('Exception on %s: %s', self::class, $e->getMessage())
            );
        }
    }

    private static function getResourceForEvent(TestUpdatedEvent $event)
    : core_kernel_classes_Resource
    {
        $eventData = json_decode(json_encode($event->jsonSerialize()));

        if (empty($eventData->testUri)) {
            throw new RuntimeException('Missing testUri');
        }

        return new core_kernel_classes_Resource($eventData->testUri);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function getProcessor(): ResourceIndexationProcessor
    {
        $container = ServiceManager::getServiceManager()->getContainer();
        return $container->get(ResourceIndexationProcessor::class);
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

    /**
     * @throws UnsupportedEventException
     */
    private function assertIsResourceDeletedEvent($event): void
    {
        if (!($event instanceof ResourceDeleted)) {
            throw new UnsupportedEventException(ResourceDeleted::class);
        }
    }

    /**
     * @throws UnsupportedEventException
     */
    private function assertIsTestUpdatedEvent($event): void
    {
        if (!($event instanceof TestUpdatedEvent)) {
            throw new UnsupportedEventException(TestUpdatedEvent::class);
        }
    }
}
