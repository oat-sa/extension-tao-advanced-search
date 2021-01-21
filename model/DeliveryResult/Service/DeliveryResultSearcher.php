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

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\task\migration\ResultUnit;
use oat\tao\model\task\migration\ResultUnitCollection;
use oat\tao\model\task\migration\service\ResultFilter;
use oat\tao\model\task\migration\service\ResultSearcherInterface;
use oat\taoDelivery\model\execution\DeliveryExecutionService;
use oat\taoOutcomeUi\model\Builder\ResultsServiceBuilder;
use oat\taoOutcomeUi\model\ResultsService;

class DeliveryResultSearcher extends ConfigurableService implements ResultSearcherInterface
{
    use OntologyAwareTrait;

    public function search(ResultFilter $filter): ResultUnitCollection
    {
        //FIXME @TODO Remove after tests
        \common_Logger::e(__METHOD__ . ' =============================> MAX ' . $filter->getParameter('max')); //FIXME
        \common_Logger::e(__METHOD__ . ' =============================> START ' . $filter->getParameter('start')); //FIXME
        \common_Logger::e(__METHOD__ . ' =============================> END ' . $filter->getParameter('end')); //FIXME

        $results = $this->getResultsService()
            ->getImplementation()
            ->getResultByDelivery(
                [],
                [
                    'offset' => $filter->getParameter('start'),
                    'limit' => $filter->getParameter('end') - $filter->getParameter('start'),
                    'recursive' => true,
                ]
            );

        $collection = new ResultUnitCollection();

        //FIXME @TODO Remove after tests
        \common_Logger::e(__METHOD__ . ' =============================> results ' . count($results));

        foreach ($results as $result) {
            $deliveryExecution = $this->getDeliveryExecutionService()
                ->getDeliveryExecution($result['deliveryResultIdentifier']);

            $collection->add(new ResultUnit($deliveryExecution));
        }

        return $collection;
    }

    private function getResultsService(): ResultsService
    {
        return $this->getResultServiceBuilder()->build();
    }

    private function getResultServiceBuilder(): ResultsServiceBuilder
    {
        return $this->getServiceLocator()
            ->get(ResultsServiceBuilder::class);
    }

    private function getDeliveryExecutionService(): DeliveryExecutionService
    {
        return $this->getServiceLocator()
            ->get(DeliveryExecutionService::SERVICE_ID);
    }
}
