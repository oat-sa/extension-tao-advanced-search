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
 * Copyright (c) 2025 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\scripts\install;

use oat\oatbox\extension\InstallAction;
use oat\tao\model\resources\relation\service\ResourceRelationServiceProxy;
use oat\taoAdvancedSearch\model\Resource\Service\ItemClassRelationService;
use oat\taoAdvancedSearch\model\Resource\Service\ItemRelationsService;

class RegisterItemRelationsService extends InstallAction
{
    public function __invoke($params)
    {
        $resourceRelationServiceProxy = $this->getServiceManager()->get(ResourceRelationServiceProxy::SERVICE_ID);
        $resourceRelationServiceProxy->addService('test', ItemRelationsService::class);
        $resourceRelationServiceProxy->addService('itemClass', ItemClassRelationService::class);
        $this->getServiceManager()->register(
            ResourceRelationServiceProxy::SERVICE_ID,
            $resourceRelationServiceProxy
        );
    }
}
