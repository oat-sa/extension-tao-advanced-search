<?php

namespace oat\taoAdvancedSearch\model\Index\Service;

use oat\oatbox\service\ConfigurableService;
use oat\tao\model\search\tasks\AddSearchIndexFromArray;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;

class ResultIndexer extends ConfigurableService implements IndexerInterface
{
    /** @var NormalizerInterface */
    private $normalizer;

    public function setNormalizer(NormalizerInterface $normalizer): self
    {
        $this->normalizer = $normalizer;

        return $this;
    }

    public function addIndex($resource): void
    {
        $normalizedResource = $this->normalizer->normalize($resource);

        $this->getQueueDispatcher()->createTask(
            new AddSearchIndexFromArray(),
            [
                $normalizedResource->getId(),
                $normalizedResource->getData()
            ],
            __('Adding/Updating search index for %s', $normalizedResource->getLabel())
        );
    }

    private function getQueueDispatcher(): QueueDispatcherInterface
    {
        return $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);
    }
}
