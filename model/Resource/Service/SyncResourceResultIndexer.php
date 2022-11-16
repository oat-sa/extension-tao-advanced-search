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

namespace oat\taoAdvancedSearch\model\Resource\Service;

use core_kernel_classes_Resource;
use oat\generis\model\data\event\ResourceUpdated;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\taoAdvancedSearch\model\Index\Listener\AgnosticEventListener;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;
use oat\taoTests\models\event\TestUpdatedEvent;

/**
 * This service is used by tasks and command line tools.
 */
class SyncResourceResultIndexer extends ConfigurableService implements IndexerInterface
{
    use OntologyAwareTrait;

    public function addIndex($resource): void
    {
        if (!$resource instanceof core_kernel_classes_Resource) {
            return;
        }

        // Forward an event to the AgnosticEventListener and let it decide
        // how to (re)index the resource
        //
        // @todo Maybe this decision logic should be somewhere else
        //
        if ($this->isTest($resource)) {
            $event = new TestUpdatedEvent($resource->getUri());
        } else {
            $event = new ResourceUpdated($resource->getUri());
        }

        $this->getListener()->listen($event);
    }

    private function isTest(core_kernel_classes_Resource $resource): bool
    {
        foreach ($resource->getTypes() as $type) {
            if ($this->isTestType($type->getUri())) {
                return true;
            }
        }

        return false;
    }

    private function isTestType(string $type): bool
    {
        return in_array(TaoOntology::CLASS_URI_TEST, [$type], true);
    }

    private function getListener(): AgnosticEventListener
    {
        return $this->getServiceManager()->getContainer()->get(
            AgnosticEventListener::class
        );
    }
}
