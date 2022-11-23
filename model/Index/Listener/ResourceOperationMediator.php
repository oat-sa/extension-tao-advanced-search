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
use oat\generis\model\data\event\ResourceUpdated;
use oat\tao\model\TaoOntology;
use oat\taoTests\models\event\TestUpdatedEvent;

/**
 * This service receives resource instances and triggers an event though the
 * provided event listener matching the called method and resource type.
 */
class ResourceOperationMediator
{
    /** @var AgnosticEventListener */
    private $eventListener;

    public function __construct(AgnosticEventListener $eventListener)
    {
        $this->eventListener = $eventListener;
    }

    public function handleAddIndex(core_kernel_classes_Resource $resource): void
    {
        if ($this->isOfType(TaoOntology::CLASS_URI_TEST, $resource)) {
            $event = new TestUpdatedEvent($resource->getUri());
        } else {
            $event = new ResourceUpdated($resource);
        }

        $this->eventListener->listen($event);
    }

    private function isOfType(
        string $type,
        core_kernel_classes_Resource $resource
    ): bool {
        foreach ($resource->getTypes() as $resourceType) {
            if ($type === $resourceType->getUri()) {
                return true;
            }
        }

        return false;
    }
}
