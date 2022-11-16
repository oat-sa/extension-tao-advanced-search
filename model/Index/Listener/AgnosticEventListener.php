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

use oat\taoAdvancedSearch\model\Index\Handler\EventHandlerInterface;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class AgnosticEventListener implements ListenerInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var EventHandlerInterface[] */
    private $handlers;

    public function __construct(LoggerInterface $logger, array $handlers)
    {
        $this->logger = $logger;
        $this->handlers = $handlers;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws UnsupportedEventException
     */
    public function listen($event): void
    {
        $eventClass = get_class($event);

        $this->assertIsSupportedEvent($eventClass);

        foreach ($this->handlers[$eventClass] as $handler) {
            try {
                $handler->handle($event);
            } catch (Throwable $e) {
                $this->logException(get_class($handler), $e);
            }
        }
    }

    /**.
     * @throws UnsupportedEventException
     */
    private function assertIsSupportedEvent(string $eventClass): void
    {
        if (!isset($this->handlers[$eventClass])) {
            throw new UnsupportedEventException(
                sprintf(
                    'one of [%s]',
                    implode(', ', array_keys($this->handlers))
                )
            );
        }
    }

    private function logException(string $eventClass, Throwable $e): void
    {
        $this->logger->warning(
            sprintf(
                'Got exception running handler %s: %s',
                $eventClass,
                $e->getMessage()
            ),
            [
                'exception' => $e->getMessage()
            ]
        );
    }
}
