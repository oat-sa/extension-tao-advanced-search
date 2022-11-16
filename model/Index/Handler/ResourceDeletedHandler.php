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

use oat\generis\model\data\event\ResourceDeleted;
use oat\oatbox\event\Event;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use Psr\Log\LoggerInterface;

class ResourceDeletedHandler implements EventHandlerInterface
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @throws UnsupportedEventException
     */
    public function handle(Event $event): void
    {
        $this->logger->info(
            self::class.'::handleResourceDeletedEvent called'
        );

        $this->assertIsResourceDeletedEvent($event);

        // @todo Reomve the resource URI from all resources in index holding a
        //       reference to its URI
        // $this->resourceIndexer->addIndex(...);
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
}
