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

namespace oat\taoAdvancedSearch\model\Resource\Repository;

use oat\generis\model\kernel\persistence\smoothsql\search\ComplexSearchService;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\search\base\ResultSetInterface;
use oat\taoAdvancedSearch\model\Resource\Cache\CacheIndexableResourceUrisService;

class IndexableResourceRepository extends ConfigurableService implements IndexableResourceRepositoryInterface
{
    use OntologyAwareTrait;

    public function findAll(string $classUri, int $offset, int $limit): ResultSetInterface
    {
        $search = $this->getComplexSearchService();

        $queryBuilder = $search->query()
            ->setLimit($limit)
            ->setOffset($offset);

        $criteria = $search->searchType($queryBuilder, $classUri, true);

        $queryBuilder = $queryBuilder->setCriteria($criteria);

        return $search->getGateway()->search($queryBuilder);
    }

    public function getTotal(string $classUri): int
    {
        $search = $this->getComplexSearchService();

        $queryBuilder = $search->query();

        $criteria = $search->searchType($queryBuilder, $classUri, true);

        $queryBuilder = $queryBuilder->setCriteria($criteria);

        return $search->getGateway()->count($queryBuilder);
    }

    private function getComplexSearchService(): ComplexSearchService
    {
        return $this->getServiceLocator()->get(ComplexSearchService::SERVICE_ID);
    }
}
