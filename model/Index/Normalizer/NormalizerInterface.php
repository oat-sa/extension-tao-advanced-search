<?php

namespace oat\taoAdvancedSearch\model\Index\Normalizer;

use oat\taoAdvancedSearch\model\Index\IndexResource;

interface NormalizerInterface
{
    public function normalize($resource): IndexResource;
}
