<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Index\Service;

use InvalidArgumentException;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\task\migration\ResultUnit;

class IndexUnitProcessor extends ConfigurableService implements IndexUnitProcessorInterface
{
    /** @var IndexerInterface */
    private $indexer;

    public function setIndexer(IndexerInterface $indexer): IndexUnitProcessorInterface
    {
        $this->indexer = $indexer;

        return $this;
    }

    public function process(ResultUnit $unit): void
    {
        if ($this->indexer === null) {
            throw new InvalidArgumentException('Indexer must be provided');
        }

        $this->indexer->addIndex($unit->getResult());
    }
}
