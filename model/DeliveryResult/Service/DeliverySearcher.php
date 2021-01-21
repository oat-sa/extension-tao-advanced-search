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

namespace oat\taoAdvancedSearch\model\DeliveryResult\Service;

use oat\generis\model\kernel\persistence\smoothsql\search\ComplexSearchService;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;

class DeliverySearcher extends ConfigurableService
{
    use OntologyAwareTrait;

    public function getAllDeliveryIds(): array
    {
        $search = $this->getComplexSearch();

        $queryBuilder = $search->query();

        $criteria = $search->searchType(
            $queryBuilder,
            'http://www.tao.lu/Ontologies/TAODelivery.rdf#AssembledDelivery',
            true
        );

        $queryBuilder = $queryBuilder->setCriteria($criteria);

        $deliveryIds = [];

        foreach ($search->getGateway()->search($queryBuilder) as $resource) {
            $deliveryIds[] = $resource->getUri();
        }

        return $deliveryIds;
    }

    private function getComplexSearch(): ComplexSearchService
    {
        return $this->getServiceLocator()->get(ComplexSearchService::SERVICE_ID);
    }
}
