<?php

namespace oat\taoAdvancedSearch\model\DeliveryResult\Service;

use oat\taoAdvancedSearch\model\Index\Service\AbstractIndexPopulator;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;

class DeliveryResultIndexPopulator extends AbstractIndexPopulator
{
    protected function getIndexer(): IndexerInterface
    {
        return $this->getServiceLocator()->get(DeliveryResultIndexer::class);
    }
}
