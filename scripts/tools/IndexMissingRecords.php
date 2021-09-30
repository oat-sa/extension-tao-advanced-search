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
use oat\search\base\ResultSetInterface;
use oat\tao\elasticsearch\ElasticSearch;
use oat\tao\elasticsearch\IndexerInterface;
use oat\tao\elasticsearch\Query;
use oat\tao\model\search\SearchProxy;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Resource\Service\SyncResourceResultIndexer;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

/**
 * php index.php 'oat\taoAdvancedSearch\scripts\tools\IndexMissingRecords' -h
 */
class IndexMissingRecords extends ScriptAction implements ServiceLocatorAwareInterface
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
                'description' => 'Resource class URI',
                'defaultValue' => TaoOntology::CLASS_URI_ITEM
            ],
            'reindex' => [
                'prefix' => 'r',
                'longPrefix' => 'reindex',
                'flag' => true,
                'description' => 'Force reindex missing resources',
                'defaultValue' => false
            ],
            'offset' => [
                'prefix' => 'o',
                'cast' => 'int',
                'longPrefix' => 'offset',
                'flag' => false,
                'description' => 'offset of search results',
                'defaultValue' => 0
            ],
            'limit' => [
                'prefix' => 'l',
                'cast' => 'int',
                'longPrefix' => 'limit',
                'flag' => false,
                'description' => 'limit of search results',
                'defaultValue' => 1000
            ]
        ];
    }

    protected function provideDescription(): string
    {
        return 'Show indexation summary';
    }

    /**
     * @inheritDoc
     */
    protected function run(): Report
    {
        $class = $this->getOption('class');
        $offset = $this->getOption('offset');
        $limit = $this->getOption('limit');
        $reindex = boolval($this->getOption('reindex'));
        $missingResources = 0;
        $missingResourcesIndexed = 0;

        $advancedSearch = $this->getElasticSearch();
        $indexer = $this->getSyncResourceResultIndexer();

        $missingIndexReport = Report::createWarning('Missing resources');
        $reIndexedReport = Report::createSuccess('ReIndexed resources');

        $mainReport = Report::createInfo(sprintf('Resources not indexed for class %s', $class));
        $mainReport->add($missingIndexReport);
        $mainReport->add($reIndexedReport);

        /** @var core_kernel_classes_Resource $resource */
        foreach ($this->search($class, $offset, $limit) as $resource) {
            if (!$resource->exists()) {
                continue;
            }

            if ($this->isIndexed($advancedSearch, $this->getIndexName($class), $resource->getUri())) {
                continue;
            }

            $missingResources++;

            $reportMessage = sprintf('%s (%s)', $resource->getUri(), $resource->getLabel());

            $missingIndexReport->add(Report::createInfo($reportMessage));

            if (!$reindex) {
                continue;
            }

            $reIndexedReport->add(Report::createInfo($reportMessage));

            $indexer->addIndex($resource);

            $missingResourcesIndexed++;
        }

        $summaryReport = Report::createSuccess('Summary');
        $summaryReport->add(Report::createInfo('Missing resources: ' . $missingResources));
        $summaryReport->add(Report::createInfo('Missing resources indexed: ' . $missingResourcesIndexed));

        $mainReport->add($summaryReport);

        return $mainReport;
    }

    private function isIndexed(ElasticSearch $search, string $index, string $uri): bool
    {
        $query = new Query($index);
        $query->addCondition(sprintf('_id:"%s"', $uri));
        $query->setLimit(1);

        return $search->search($query)->getTotalCount() > 0;
    }

    private function getIndexName(string $classUri): ?string
    {
        //@TODO Remove direct call for ElasticSearch
        return IndexerInterface::AVAILABLE_INDEXES[$classUri] ?? null;
    }

    private function search(string $classUri, int $offset, int $limit): ResultSetInterface
    {
        $search = $this->getComplexSearchService();

        $queryBuilder = $search->query();

        $criteria = $search->searchType($queryBuilder, $classUri, true);

        $queryBuilder = $queryBuilder->setCriteria($criteria);

        $queryBuilder->setOffset($offset);

        if ($limit > 0) {
            $queryBuilder->setLimit($limit);
        }

        return $search->getGateway()->search($queryBuilder);
    }

    private function getElasticSearch(): ElasticSearch
    {
        return $this->getServiceLocator()->get(SearchProxy::SERVICE_ID)->getAdvancedSearch();
    }

    private function getComplexSearchService(): ComplexSearchService
    {
        return $this->getServiceLocator()->get(ComplexSearchService::SERVICE_ID);
    }

    private function getSyncResourceResultIndexer(): SyncResourceResultIndexer
    {
        return $this->getServiceManager()->get(SyncResourceResultIndexer::class);
    }
}
