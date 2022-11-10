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
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Resource\Service;

use core_kernel_classes_Resource;
use oat\tao\model\search\tasks\UpdateResourceInIndex;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;

class ResourceIndexer implements IndexerInterface
{
    /** @var QueueDispatcherInterface */
    private $queueDispatcher;

    public function __construct(QueueDispatcherInterface $queueDispatcher)
    {
        $this->queueDispatcher = $queueDispatcher;
    }

    public function addIndex($resource): void
    {
        // Not called
        \common_Logger::singleton()->logInfo(
            "Hello from ResourceIndexer"
        );

        $resources = is_array($resource) ? $resource : [$resource];
        $resourcesProcessed = [];

        foreach ($resources as $resourceIn) {
            $resourcesProcessed[] = $resourceIn instanceof core_kernel_classes_Resource
                ? $resourceIn->getUri()
                : $resourceIn;
        }

        $this->queueDispatcher->createTask(
            new UpdateResourceInIndex(),
            [
                $resourcesProcessed
            ],
            sprintf('Indexing resource(s) %s...', substr(implode(',', $resourcesProcessed), 0, 100))
        );
    }
}
