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

namespace oat\taoAdvancedSearch\model\Metadata\Factory;

use oat\generis\model\OntologyAwareTrait;
use oat\tao\model\task\migration\service\ResultFilterFactory;
use oat\tao\model\task\migration\service\ResultFilterFactoryInterface;
use oat\taoAdvancedSearch\model\Metadata\Service\MetadataResultSearcher;

class MetadataResultFilterFactory extends ResultFilterFactory implements ResultFilterFactoryInterface
{
    use OntologyAwareTrait;

    protected function getMax(): int
    {
        $count = 0;
        foreach (MetadataResultSearcher::ROOT_CLASSES as $class) {
            //Plus 1 for root class itself.
            $count += $this->getClass($class)->countInstances([], ['recursive' => true]) + 1;
        }

        return $count;
    }
}
