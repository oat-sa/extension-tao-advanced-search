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

namespace oat\taoAdvancedSearch\model\DeliveryResult\Factory;

use core_kernel_classes_Resource;
use oat\generis\model\kernel\persistence\smoothsql\search\ResourceSearchService;
use oat\tao\model\TaoOntology;
use oat\tao\model\task\migration\service\ResultFilterFactory;
use oat\tao\model\task\migration\service\ResultFilterFactoryInterface;
use oat\taoOutcomeUi\model\Builder\ResultsServiceBuilder;
use oat\taoOutcomeUi\model\ResultsService;

class DeliveryResultFilterFactory extends ResultFilterFactory implements ResultFilterFactoryInterface
{
    protected function getMax(): int
    {
        $deliveryIds = [];

        $resultSet = $this->getResourceSearchService()
            ->findByClassUri(TaoOntology::CLASS_URI_ASSEMBLED_DELIVERY);

        /** @var core_kernel_classes_Resource $result */
        foreach ($resultSet as $result) {
            $deliveryIds[] = $result->getUri();
        }

        return (int)$this->getResultsService()
            ->getImplementation()
            ->countResultByDelivery($deliveryIds);
    }

    private function getResultsService(): ResultsService
    {
        return $this->getResultServiceBuilder()->build();
    }

    private function getResultServiceBuilder(): ResultsServiceBuilder
    {
        return $this->getServiceLocator()->get(ResultsServiceBuilder::class);
    }

    private function getResourceSearchService(): ResourceSearchService
    {
        return $this->getServiceLocator()->get(ResourceSearchService::class);
    }
}
