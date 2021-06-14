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

namespace oat\taoAdvancedSearch\model\Index\Report;

use oat\oatbox\service\ConfigurableService;
use oat\tao\elasticsearch\ElasticSearch;
use oat\tao\elasticsearch\IndexerInterface;
use oat\tao\elasticsearch\Query;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\DeliveryResult\Repository\DeliveryResultRepository;
use oat\taoAdvancedSearch\model\DeliveryResult\Repository\DeliveryResultRepositoryInterface;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriCachedRepository;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriRepositoryInterface;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepository;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepositoryInterface;

class IndexSummarizer extends ConfigurableService
{
    public function summarize(): array
    {
        $output = [];

        foreach ($this->getIndexableClassRepository()->findAll() as $class) {
            $output[] = $this->createReport(
                $class->getLabel() . ' (' . $class->getUri() . ')',
                $this->getIndexName($class->getUri()),
                $class->countInstances([], ['recursive' => true])
            );
        }

        $output[] = $this->createReport(
            'Metadata',
            IndexerInterface::PROPERTY_LIST, //@TODO Remove direct call for ElasticSearch
            $this->getClassUriRepository()->getTotal()
        );

        $output[] = $this->createReport(
            'Delivery Results',
            IndexerInterface::DELIVERY_RESULTS_INDEX, //@TODO Remove direct call for ElasticSearch
            $this->getDeliveryResultRepository()->getTotal()
        );

        return $output;
    }

    private function getIndexName(string $classUri): ?string
    {
        //@TODO Remove direct call for ElasticSearch
        return IndexerInterface::AVAILABLE_INDEXES[$classUri] ?? null;
    }

    private function getTotalResults(string $index): int
    {
        /** @var ElasticSearch $advancedSearch */
        $advancedSearch = $this->getSearchProxy()->getAdvancedSearch(); //@TODO Remove direct call for ElasticSearch

        $query = new Query($index);
        $query->addCondition('*');
        $query->setLimit(1);

        return $advancedSearch->search($query)->getTotalCount();
    }

    private function createReport(
        string $label,
        string $index,
        int $totalInDb
    ): array
    {
        $totalIndexed = $this->getTotalResults($index);
        $percentageIndexed = $totalIndexed === 0 || $totalInDb === 0 ? 0 : (float)min(round($totalIndexed / $totalInDb * 100, 2), 100);
        $totalMissingIndexation = $totalInDb - $totalIndexed;

        return [
            'label' => $label,
            'index' => $index,
            'totalInDb' => $totalInDb,
            'totalIndexed' => $totalIndexed,
            'totalMissingIndexation' => $totalMissingIndexation,
            'percentageIndexed' => $percentageIndexed,
        ];
    }

    private function getIndexableClassRepository(): IndexableClassRepositoryInterface
    {
        return $this->getServiceLocator()->get(IndexableClassRepository::class);
    }

    private function getSearchProxy(): SearchProxy
    {
        return $this->getServiceLocator()->get(SearchProxy::SERVICE_ID);
    }

    private function getClassUriRepository(): ClassUriRepositoryInterface
    {
        return $this->getServiceLocator()->get(ClassUriCachedRepository::class);
    }

    private function getDeliveryResultRepository(): DeliveryResultRepositoryInterface
    {
        return $this->getServiceLocator()->get(DeliveryResultRepository::class);
    }
}
