<?php

namespace oat\taoAdvancedSearch\model\DeliveryResult\Service;

use oat\taoAdvancedSearch\model\DeliveryResult\Normalizer\DeliveryResultNormalizer;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;
use oat\taoAdvancedSearch\model\Index\Service\AbstractResultIndexer;

class DeliveryResultIndexer extends AbstractResultIndexer
{
    protected function getNormalizer(): NormalizerInterface
    {
        return $this->getServiceLocator()->get(DeliveryResultNormalizer::class);
    }
}
