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

use oat\oatbox\service\ConfigurableService;
use oat\tao\model\task\migration\ResultUnit;
use oat\tao\model\task\migration\ResultUnitCollection;
use oat\tao\model\task\migration\service\ResultFilter;
use oat\tao\model\task\migration\service\ResultSearcherInterface;
use oat\taoDelivery\model\execution\DeliveryExecutionService;
use oat\taoOutcomeUi\model\ResultsService;
use oat\taoOutcomeUi\model\Wrapper\ResultServiceWrapper;
use oat\taoResultServer\models\classes\ResultServerService;

class DeliveryResultSearcher extends ConfigurableService implements ResultSearcherInterface
{
    public function search(ResultFilter $filter): ResultUnitCollection
    {
        $filter->getParameter('max');
        $filter->getParameter('start');
        $filter->getParameter('end');

        $deliveryId = 'https://tao.docker.localhost/ontologies/tao.rdf#i6006f70fd479a37700506c282d16be90';

        $results = $this->getResultsService($deliveryId)
            ->getImplementation()
            ->getResultByDelivery(
                [
                    //@TODO FIXME Decide how to get the deliveries

                ],
                [
                    //'order' => $this->getRequestParameter('sortby'),
                    //'orderdir' => strtoupper($this->getRequestParameter('sortorder')),
                    'offset' => $filter->getParameter('start') ?? 0,
                    'limit' => $filter->getParameter('max') ?? 1,
                    'recursive' => true,
                ]
            );


        $collection = new ResultUnitCollection();

        foreach ($results as $result) {
            $deliveryExecution = $this->getDeliveryExecutionService()
                ->getDeliveryExecution($result['deliveryResultIdentifier']);

            $collection->add(new ResultUnit($deliveryExecution));
        }

        return $collection;
    }

    private function getResultsService(string $deliveryUri): ResultsService
    {
        /** @var ResultsService $service */
        $service = $this->getServiceLocator()
            ->get(ResultServiceWrapper::SERVICE_ID)
            ->getService();

        $resultServerService = $this->getServiceLocator()->get(ResultServerService::SERVICE_ID);

        $resultStorage = $resultServerService->getResultStorage($deliveryUri);

        $service->setImplementation($resultStorage);

        return $service;
    }

    private function getDeliveryExecutionService(): DeliveryExecutionService
    {
        return $this->getServiceLocator()
            ->get(DeliveryExecutionService::SERVICE_ID);
    }
}
