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
use oat\generis\model\data\event\ResourceUpdated;
use oat\taoAdvancedSearch\model\Index\Listener\ListenerInterface;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexationProcessor;
use Exception;
use Psr\Log\LoggerInterface;

class ResourceUpdatedListener implements ListenerInterface
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

    /**
     * @throws Exception
     */
    public function listen($event): void
    {
        try {
            $this->logger->info(self::class.' called');
            $this->assertIsSupportedEvent($event);

            /** @var ResourceUpdated $event */
            $this->processor->addIndex($event->getResource());
        } catch (Throwable $e) {
            $this->logException($e);
        }
    }

    /**
     * @throws UnsupportedEventException
     */
    private function assertIsSupportedEvent($event): void
    {
        if (!($event instanceof ResourceUpdated)) {
            throw new UnsupportedEventException(ResourceUpdated::class);
        }
    }

    private function logException(Throwable $e): void
    {
        $this->logger->warning(
            sprintf('Exception on %s: %s', self::class, $e->getMessage())
        );
    }
}
