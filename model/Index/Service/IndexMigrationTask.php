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

namespace oat\taoAdvancedSearch\model\Index\Service;

use oat\tao\model\task\migration\AbstractMigrationTask;
use oat\tao\model\task\migration\service\MigrationConfigFactory;
use oat\tao\model\task\migration\service\MigrationConfigFactoryInterface;
use oat\tao\model\task\migration\service\ResultFilterFactory;
use oat\tao\model\task\migration\service\ResultFilterFactoryInterface;
use oat\tao\model\task\migration\service\ResultSearcherInterface;
use oat\tao\model\task\migration\service\ResultUnitProcessorInterface;
use oat\tao\model\task\migration\service\SpawnMigrationConfigService;
use oat\tao\model\task\migration\service\SpawnMigrationConfigServiceInterface;
use oat\taoAdvancedSearch\model\DeliveryResult\Service\DeliveryResultSearcher;
use oat\taoAdvancedSearch\model\DeliveryResult\Service\DeliveryResultUnitProcessor;

class IndexMigrationTask extends AbstractMigrationTask
{
    protected function getUnitProcessor(): ResultUnitProcessorInterface
    {
        return $this->getServiceLocator()->get(DeliveryResultUnitProcessor::class);
    }

    protected function getResultSearcher(): ResultSearcherInterface
    {
        return $this->getServiceLocator()->get(DeliveryResultSearcher::class);
    }

    protected function getSpawnMigrationConfigService(): SpawnMigrationConfigServiceInterface
    {
        return $this->getServiceLocator()->get(SpawnMigrationConfigService::class);
    }

    protected function getResultFilterFactory(): ResultFilterFactoryInterface
    {
        return $this->getServiceLocator()->get(ResultFilterFactory::class); //@TODO FIXME - Needs to be replaced by proper service
    }

    protected function getMigrationConfigFactory(): MigrationConfigFactoryInterface
    {
        return $this->getServiceLocator()->get(MigrationConfigFactory::class);
    }
}
