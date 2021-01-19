<?php

namespace oat\taoAdvancedSearch\model\Index;

use ArrayIterator;

class IndexResourceCollection extends ArrayIterator
{
    public function __construct(IndexResource ...$items)
    {
        parent::__construct($items);
    }
}
