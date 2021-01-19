<?php

namespace oat\taoAdvancedSearch\model\Index\Service;

interface IndexPopulatorInterface
{
    public function populate(iterable $resources): void;
}
