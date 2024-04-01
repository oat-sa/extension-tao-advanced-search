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

namespace oat\taoAdvancedSearch\scripts\install;

use Exception;
use oat\oatbox\extension\InstallAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\search\tasks\AddSearchIndexFromArray;
use oat\tao\model\search\tasks\DeleteIndexProperty;
use oat\tao\model\search\tasks\RenameIndexProperties;
use oat\tao\model\search\tasks\UpdateClassInIndex;
use oat\tao\model\search\tasks\UpdateDataAccessControlInIndex;
use oat\tao\model\search\tasks\UpdateResourceInIndex;
use oat\tao\model\search\tasks\UpdateTestResourceInIndex;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoAdvancedSearch\model\DeliveryResult\Service\DeliveryResultMigrationTask;
use oat\taoAdvancedSearch\model\Metadata\Task\MetadataMigrationTask;
use oat\taoAdvancedSearch\model\Resource\Task\ResourceMigrationTask;
use oat\taoTaskQueue\model\Service\QueueAssociationService;

class RegisterTaskQueueServices extends InstallAction
{
    public const QUEUE_NAME = 'indexation_queue';

    public function __invoke($params)
    {
        $newQueueName = $this->getQueueName();
        $newAssociations = $this->getNewAssociations($this->getQueueName());

        try {
            $newQueue = $this->getAssociationService()->associateBulk($newQueueName, $newAssociations);
            $this->propagate($newQueue);
        } catch (Exception $exception) {
            return new Report(Report::TYPE_ERROR, $exception->getMessage());
        }

        $this->getQueueDispatcher()->initialize();

        return new Report(
            Report::TYPE_SUCCESS,
            sprintf(
                'Indexation TaskQueue `%s` registered',
                $newQueueName
            )
        );
    }

    private function getNewAssociations(string $queueName): array
    {
        return [
            UpdateTestResourceInIndex::class => $queueName,
            UpdateResourceInIndex::class => $queueName,
            UpdateClassInIndex::class => $queueName,
            DeleteIndexProperty::class => $queueName,
            RenameIndexProperties::class => $queueName,
            UpdateDataAccessControlInIndex::class => $queueName,
            AddSearchIndexFromArray::class => $queueName,
            ResourceMigrationTask::class => $queueName,
            DeliveryResultMigrationTask::class => $queueName,
            MetadataMigrationTask::class => $queueName,
        ];
    }

    public function getQueueName(): string
    {
        return self::QUEUE_NAME;
    }

    private function getAssociationService(): QueueAssociationService
    {
        return $this->getServiceManager()->get(QueueAssociationService::class);
    }

    private function getQueueDispatcher(): QueueDispatcherInterface
    {
        return $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);
    }
}
