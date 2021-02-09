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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\scripts\install;

use oat\oatbox\extension\InstallAction;
use oat\tao\model\Lists\Business\Service\ClassMetadataSearcherProxy;
use oat\taoAdvancedSearch\model\Metadata\Service\ClassMetadataSearcher;

class RegisterServices extends InstallAction
{
    public function __invoke($params = [])
    {
        $proxy = $this->getProxy();

        $proxy->setOption(ClassMetadataSearcherProxy::OPTION_ACTIVE_SEARCHER, ClassMetadataSearcher::class);

        $this->getServiceManager()->register(ClassMetadataSearcherProxy::SERVICE_ID, $proxy);
    }

    private function getProxy(): ClassMetadataSearcherProxy
    {
        return $this->getServiceManager()->get(ClassMetadataSearcherProxy::SERVICE_ID);
    }
}
