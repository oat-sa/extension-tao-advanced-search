<?php

namespace oat\taoAdvancedSearch\model\Index\Service;

use oat\oatbox\service\ConfigurableService;
use oat\tao\model\search\tasks\AddSearchIndexFromArray;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;

abstract class AbstractResultIndexer extends ConfigurableService implements IndexerInterface
{
    public function addIndex($resource): void
    {
        $normalizedResource = $this->getNormalizer()->normalize($resource);

        $this->getQueueDispatcher()->createTask(
            new AddSearchIndexFromArray(),
            [
                $normalizedResource->getId(),
                $normalizedResource->getData()
            ],
            __('Adding/Updating search index for %s', $normalizedResource->getLabel())
        );
    }

    abstract protected function getNormalizer(): NormalizerInterface;

    private function getQueueDispatcher(): QueueDispatcherInterface
    {
        return $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);
    }
}