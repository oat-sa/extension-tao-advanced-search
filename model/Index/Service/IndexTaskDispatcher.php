<?php

namespace oat\taoAdvancedSearch\model\Index\Service;

use oat\oatbox\service\ConfigurableService;
use oat\tao\model\search\tasks\AddSearchIndexFromArray;
use oat\tao\model\taskQueue\QueueDispatcherInterface;

class IndexTaskDispatcher extends ConfigurableService
{
    public function dispatch(string $id, string $label, array $data)
    {
        $this->getQueueDispatcher()->createTask(
            new AddSearchIndexFromArray(),
            [
                $id,
                $data
            ],
            __('Adding/Updating search index for %s', $label)
        );
    }

    private function getQueueDispatcher(): QueueDispatcherInterface
    {
        return $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);
    }
}
