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

namespace oat\taoAdvancedSearch\model\Resource\Service;

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\task\migration\ResultUnit;
use oat\tao\model\task\migration\ResultUnitCollection;
use oat\tao\model\task\migration\service\ResultFilter;
use oat\tao\model\task\migration\service\ResultSearcherInterface;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableResourceRepository;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableResourceRepositoryInterface;
use oat\taoDelivery\model\execution\DeliveryExecutionService;

class ResourceSearcher extends ConfigurableService implements ResultSearcherInterface
{
    use OntologyAwareTrait;

    public function search(ResultFilter $filter): ResultUnitCollection
    {
        $classUri = (string)$filter->getParameter('classUri');
        $offset = (int)$filter->getParameter('start');
        $limit = (int)($filter->getParameter('end') - $filter->getParameter('start'));

        $results = $this->getIndexableResourceUrisRepository()->findAll($classUri, $offset, $limit);

        $collection = new ResultUnitCollection();

        foreach ($results as $resource) {
            $collection->add(new ResultUnit($resource));
        }

        return $collection;
    }

    private function getIndexableResourceUrisRepository(): IndexableResourceRepositoryInterface
    {
        return $this->getServiceLocator()->get(IndexableResourceRepository::class);
    }
}
