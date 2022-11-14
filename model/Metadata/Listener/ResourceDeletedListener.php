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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Metadata\Listener;

use oat\generis\model\data\event\ResourceUpdated;
use oat\taoAdvancedSearch\model\Index\Listener\ListenerInterface;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexer;
use Exception;

class ResourceDeletedListener implements ListenerInterface
{
    /** @var ResourceIndexer */
    private $resourceIndexer;

    public function __construct(ResourceIndexer $resourceIndexer)
    {
        $this->resourceIndexer = $resourceIndexer;
    }

    /**
     * @throws Exception
     */
    public function listen($event): void
    {
        \common_Logger::singleton()->logInfo(self::class.' called');

        $this->assertIsSupportedEvent($event);


        // $this->resourceIndexer->addIndex(...);
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
}
