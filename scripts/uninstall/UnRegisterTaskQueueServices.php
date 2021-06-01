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
 * Copyright (c) 2017-2021 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */

namespace oat\taoAdvancedSearch\scripts\uninstall;

use oat\oatbox\extension\InstallAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\taskQueue\Queue;
use oat\tao\model\taskQueue\QueueDispatcher;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoAdvancedSearch\scripts\install\RegisterTaskQueueServices;

class UnRegisterTaskQueueServices extends InstallAction
{
    private const QUEUE_NAME = RegisterTaskQueueServices::QUEUE_NAME;

    public function __invoke($params)
    {
        /** @var QueueDispatcher $queueService */
        $queueService   = $this->getServiceManager()->get(QueueDispatcher::SERVICE_ID);
        $existingQueues = $queueService->getOption(QueueDispatcherInterface::OPTION_QUEUES);

        $newQueue = [];
        /** @var Queue $queue */
        foreach ($existingQueues as $queue) {
            if ($queue->getName() !== self::QUEUE_NAME) {
                $newQueue[] = $queue;
            }
        }

        $existingOptions = $queueService->getOptions();
        $existingOptions[QueueDispatcherInterface::OPTION_QUEUES] = $newQueue;

        $existingAssociations = $existingOptions[QueueDispatcherInterface::OPTION_TASK_TO_QUEUE_ASSOCIATIONS];
        $newAssociations = array_filter($existingAssociations, [$this, 'isNotIndexationQueue']);
        $existingOptions[QueueDispatcherInterface::OPTION_TASK_TO_QUEUE_ASSOCIATIONS] = $newAssociations;

        $queueService->setOptions($existingOptions);
        $this->getServiceManager()->register(QueueDispatcherInterface::SERVICE_ID, $queueService);

        return new Report(Report::TYPE_SUCCESS, 'Indexation TaskQueue unregistered');
    }

    private function isNotIndexationQueue(string $el): bool
    {
        return $el !== self::QUEUE_NAME;
    }

}
