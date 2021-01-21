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

use oat\oatbox\service\ConfigurableService;
use oat\tao\model\task\migration\MigrationConfig;
use oat\tao\model\task\migration\service\ResultFilter;
use oat\tao\model\task\migration\service\ResultFilterFactoryInterface;
use oat\taoAdvancedSearch\model\DeliveryResult\Service\DeliverySearcher;
use oat\taoAdvancedSearch\model\DeliveryResult\Service\ResultServiceBuilder;
use oat\taoOutcomeUi\model\ResultsService;

class ResultFilterFactory extends ConfigurableService implements ResultFilterFactoryInterface
{
    public function create(MigrationConfig $config): ResultFilter
    {
        $deliveryIds = $this->getDeliverySearcher()->getAllDeliveryIds();

        $max = $this->getResultsService()
            ->getImplementation()
            ->countResultByDelivery($deliveryIds);

        $end = $this->calculateEndPosition(
            (int)$config->getCustomParameter('start'),
            $config->getChunkSize(),
            $max
        );

        return new ResultFilter(
            [
                'start' => (int)$config->getCustomParameter('start'),
                'end' => $end,
                'max' => $max
            ]
        );
    }

    private function calculateEndPosition(int $start, int $chunkSize, int $max): int
    {
        $end = $start + $chunkSize;

        if ($end >= $max) {
            $end = $max;
        }
        return $end;
    }

    private function getResultsService(): ResultsService
    {
        return $this->getResultServiceBuilder()->build();
    }

    private function getResultServiceBuilder(): ResultServiceBuilder
    {
        return $this->getServiceLocator()->get(ResultServiceBuilder::class);
    }

    private function getDeliverySearcher(): DeliverySearcher
    {
        return $this->getServiceLocator()->get(DeliverySearcher::class);
    }
}
