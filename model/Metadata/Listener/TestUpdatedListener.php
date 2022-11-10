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
 * Copyright (c) 2021-2022 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Metadata\Listener;

use common_Logger;
use core_kernel_classes_Resource;
use oat\taoAdvancedSearch\model\Index\Listener\ListenerInterface;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexationProcessor;
use oat\taoTests\models\event\TestUpdatedEvent;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class TestUpdatedListener implements ListenerInterface
{
    /** @var ResourceIndexationProcessor */
    private $processor;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ResourceIndexationProcessor $processor,
        ?LoggerInterface $logger = null
    ) {
        $this->processor = $processor;
        $this->logger = $logger ?? common_Logger::singleton()->getLogger();
    }

    public function listen($event): void
    {
        try {
            $this->assertIsSupportedEvent($event);

            /** @var TestUpdatedEvent $event */
            $this->processor->addIndex($this->getResourceForEvent($event));
        } catch (Throwable $e) {
            $this->logException($e);
        }
    }

    private function getResourceForEvent(TestUpdatedEvent $event)
                                                : core_kernel_classes_Resource
    {
        $eventData = json_decode(json_encode($event->jsonSerialize()));

        if (empty($eventData->testUri)) {
            throw new RuntimeException('Missing testUri');
        }

        return new core_kernel_classes_Resource($eventData->testUri);
    }

    /**
     * @throws UnsupportedEventException
     */
    private function assertIsSupportedEvent($event): void
    {
        if (!($event instanceof TestUpdatedEvent)) {
            throw new UnsupportedEventException(TestUpdatedEvent::class);
        }
    }

    private function logException(Throwable $e): void
    {
        $this->logger->warning(
            sprintf('Exception on %s: %s', self::class, $e->getMessage())
        );
    }
}
