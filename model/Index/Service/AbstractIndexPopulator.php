<?php

namespace oat\taoAdvancedSearch\model\Index\Service;

use oat\oatbox\service\ConfigurableService;

abstract class AbstractIndexPopulator extends ConfigurableService implements IndexPopulatorInterface
{
    public function populate(iterable $resources): void
    {
        //@TODO Support pagination on this version? (upper level)
        //@TODO Support multiple workers indexation split? (upper level)
        foreach ($resources as $resource) {
            $this->getIndexer()->addIndex($resource);
        }
    }

    abstract protected function getIndexer(): IndexerInterface;
}
