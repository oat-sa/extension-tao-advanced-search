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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Resource\Factory;

use oat\tao\model\task\migration\MigrationConfig;
use oat\tao\model\task\migration\service\ResultFilter;
use oat\tao\model\task\migration\service\ResultFilterFactory;
use oat\tao\model\task\migration\service\ResultFilterFactoryInterface;
use oat\taoAdvancedSearch\model\DeliveryResult\Repository\DeliveryResultRepository;
use oat\taoAdvancedSearch\model\DeliveryResult\Repository\DeliveryResultRepositoryInterface;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableResourceRepository;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableResourceRepositoryInterface;

class ResourceResultFilterFactory extends ResultFilterFactory implements ResultFilterFactoryInterface
{
    protected function getMax(): int
    {
        $classUri = $this->config->getCustomParameter('classUri');

        if ($classUri) {
            return $this->getIndexableResourceUrisRepository()->getTotal($classUri);
        }

        return 0;
    }

    private function getIndexableResourceUrisRepository(): IndexableResourceRepositoryInterface
    {
        return $this->getServiceLocator()->get(IndexableResourceRepository::class);
    }
}
