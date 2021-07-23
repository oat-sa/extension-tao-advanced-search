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

namespace oat\taoAdvancedSearch\scripts\tools;

use core_kernel_classes_Resource;
use oat\generis\model\kernel\persistence\smoothsql\search\ComplexSearchService;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\search\helper\SupportedOperatorHelper;
use oat\tao\elasticsearch\ElasticSearch;
use oat\tao\elasticsearch\IndexerInterface;
use oat\tao\elasticsearch\Query;
use oat\tao\model\search\SearchProxy;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class GarbageCollector extends ScriptAction implements ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;
    use OntologyAwareTrait;

    protected function provideUsage(): array
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Type this option to see the parameters.'
        ];
    }

    protected function provideOptions(): array
    {
        return [
            'class' => [
                'prefix' => 'c',
                'longPrefix' => 'class',
                'flag' => false,
                'description' => 'The class URI',
                'required' => true,
            ],
            'index' => [
                'prefix' => 'i',
                'longPrefix' => 'index',
                'flag' => false,
                'description' => 'The index to clean',
                'required' => true,
            ],
        ];
    }

    protected function provideDescription(): string
    {
        return 'Clear invalid indexes';
    }

    /**
     * @inheritDoc
     */
    protected function run(): Report
    {
        $report = Report::createInfo('Cleaning indexes...');

        // 1 - Select all URIs from
        $pageLimit = 100;
        $classUri = $this->getOption('class');
        $index = $this->getOption('index');

        $offset = 0;

        do {
            $removed = $this->removeNotExisting($classUri, $index, $offset, $pageLimit);
            $offset += $pageLimit;

            $report->add(
                Report::createSuccess(
                    sprintf(
                        '%s document(s) removed for class "%s" in the index "%s"',
                        count($removed),
                        $classUri,
                        $index
                    )
                )
            );
        } while ($removed !== null);

        $report->add(
            Report::createSuccess(
                sprintf('Indexes cleaned up')
            )
        );

        return $report;
    }

    private function removeNotExisting(string $classUri, string $index, int $offset, int $limit): ?array
    {
        // 1 - Search class resources by URIs
        $query = new Query($index);
        $query->setLimit($offset);
        $query->setOffset($limit);

        $searcher = $this->getSearch();
        $results = $searcher->search($query);

        $uris = [];

        if ($results->getTotalCount() === 0) {
            return null;
        }

        foreach ($results as $result) {
            $uris[] = $result['_id'];
        }

        // 2 - Search existing resources....
        $search = $this->getComplexSearchService();
        $queryBuilder = $search->query()
            ->setLimit($limit)
            ->setOffset($offset);

        $criteria = $search->searchType($queryBuilder, $classUri, true);

        $criteria->addCriterion('uri', SupportedOperatorHelper::IN, $uris);

        $queryBuilder = $queryBuilder->setCriteria($criteria);

        $resources = $search->getGateway()->search($queryBuilder);

        // 3 - Unset non existing URIs
        /** @var core_kernel_classes_Resource $resource */
        foreach ($resources as $resource) {
            $keys = array_keys($uris, $resource->getUri());

            foreach ($keys as $key) {
                unset($uris[$key]);
            }
        }

        // 4 - Remove non existing
        foreach ($uris as $uri) {
            $searcher->remove($uri);
        }

        return $uris;
    }

    private function getComplexSearchService(): ComplexSearchService
    {
        return $this->getServiceLocator()->get(ComplexSearchService::SERVICE_ID);
    }

    private function getSearch(): ElasticSearch
    {
        /** @var SearchProxy $proxy */
        $proxy = $this->getServiceLocator()->get(SearchProxy::SERVICE_ID);

        /** @var ElasticSearch $search */
        $search = $proxy->getAdvancedSearch();

        return $search;
    }
}
