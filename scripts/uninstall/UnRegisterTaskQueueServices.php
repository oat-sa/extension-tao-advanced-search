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
 *
 */

namespace oat\taoAdvancedSearch\scripts\uninstall;

use oat\oatbox\extension\InstallAction;
use oat\oatbox\reporting\Report;
use oat\taoAdvancedSearch\scripts\install\RegisterTaskQueueServices;
use oat\taoTaskQueue\model\Service\QueueAssociationService;

class UnRegisterTaskQueueServices extends InstallAction
{
    private const QUEUE_NAME = RegisterTaskQueueServices::QUEUE_NAME;

    public function __invoke($params)
    {
        $queueName = self::QUEUE_NAME;

        $this->getAssociationService()->deleteAndRemoveAssociations($queueName);

        return new Report(Report::TYPE_SUCCESS, 'Indexation TaskQueue `%s` was unregistered', $queueName);
    }

    private function getAssociationService(): QueueAssociationService{
        return $this->getServiceManager()->get(QueueAssociationService::class);
    }

}
