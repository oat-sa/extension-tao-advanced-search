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
                'flag' => false,
                'description' => 'Force reindex missing resources',
                'defaultValue' => false
            ],
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
        $reindex = boolval($this->getOption('reindex'));
        $mainReport = Report::createInfo(sprintf('Resources not indexed for class %s', $class));
        $missingIndexReport = Report::createWarning('Missing resources');
        $mainReport->add($missingIndexReport);
        $missingResources = [];
        $missingResourcesIndexed = [];

        /** @var core_kernel_classes_Resource $resource */
        foreach ($this->search($class) as $resource) {
            if (!$resource->exists()) {
                continue;
            }

            if (!$this->isIndexed($this->getIndexName($class), $resource->getUri())) {
                $missingResources[$resource->getUri()] = $resource;

                $missingIndexReport->add(
                    Report::createInfo(
                        $resource->getUri() . ' (' . $resource->getLabel() . ')'
                    )
                );
            }
        }

        if ($reindex) {
            $reIndexedReport = Report::createSuccess('ReIndexed resources');
            $mainReport->add($reIndexedReport);

            foreach ($missingResources as $resource) {
                /** @var SyncResourceResultIndexer $indexer */
                $indexer = $this->getServiceManager()->get(SyncResourceResultIndexer::class);

                $reIndexedReport->add(
                    Report::createInfo(
                        $resource->getUri() . ' (' . $resource->getLabel() . ')'
                    )
                );

                $indexer->addIndex($resource);

                $missingResourcesIndexed[$resource->getUri()] = $resource->getLabel();
            }
        }

        $summaryReport = Report::createSuccess('Summary');
        $summaryReport->add(Report::createInfo('Missing resources: ' . count($missingResources)));
        $summaryReport->add(Report::createInfo('Missing resources indexed: ' . count($missingResourcesIndexed)));

        $mainReport->add($summaryReport);

        return $mainReport;
    }

    private function isIndexed(string $index, string $uri)
    {
        $advancedSearch = $this->getElasticSearch();

        $query = new Query($index);
        $query->addCondition('_id:"' . $uri . '"');
        $query->setLimit(1);

        return $advancedSearch->search($query)->getTotalCount() > 0;
    }

    private function getIndexName(string $classUri): ?string
    {
        //@TODO Remove direct call for ElasticSearch
        return IndexerInterface::AVAILABLE_INDEXES[$classUri] ?? null;
    }

    private function search(string $classUri): ResultSetInterface
    {
        $search = $this->getComplexSearchService();

        $queryBuilder = $search->query();

        $criteria = $search->searchType($queryBuilder, $classUri, true);

        $queryBuilder = $queryBuilder->setCriteria($criteria);

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
}
