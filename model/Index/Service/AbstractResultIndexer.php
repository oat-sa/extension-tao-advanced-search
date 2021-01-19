<?php

namespace oat\taoAdvancedSearch\model\Index\Service;

use oat\oatbox\service\ConfigurableService;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;

abstract class AbstractResultIndexer extends ConfigurableService implements IndexerInterface
{
    public function addIndex($resource): void
    {
        $normalizedResource = $this->getNormalizer()->normalize($resource);

        $this->getIndexTaskDispatcher()->dispatch(
            $normalizedResource->getId(),
            $normalizedResource->getLabel(),
            $normalizedResource->getData()
        );
    }

    abstract protected function getNormalizer(): NormalizerInterface;

    private function getIndexTaskDispatcher(): IndexTaskDispatcher
    {
        return $this->getServiceLocator()->get(IndexTaskDispatcher::class);
    }
}