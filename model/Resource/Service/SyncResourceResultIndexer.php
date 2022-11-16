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
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\taoAdvancedSearch\model\Index\Listener\ResourceOperationAdapter;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;

/**
 * This service is used by tasks and command line tools.
 */
class SyncResourceResultIndexer extends ConfigurableService implements IndexerInterface
{
    use OntologyAwareTrait;

    public function addIndex($resource): void
    {
        // Use the adapter to forward an event to the AgnosticEventListener
        // and let it decide how to (re)index the resource based on the
        // container config
        //
        if ($resource instanceof core_kernel_classes_Resource) {
            $this->getAdapter()->handleAddIndex($resource);
        }
    }

    private function getAdapter(): ResourceOperationAdapter
    {
        return $this->getServiceManager()->getContainer()->get(
            ResourceOperationAdapter::class
        );
    }
}
