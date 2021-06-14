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

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\elasticsearch\ElasticSearch;
use oat\tao\elasticsearch\IndexerInterface;
use oat\tao\elasticsearch\Query;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriCachedRepository;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriRepositoryInterface;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepository;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepositoryInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class IndexSummary extends ScriptAction implements ServiceLocatorAwareInterface
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
        return [];
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
        $mainReport = Report::createSuccess('Index vs Storage');

        foreach ($this->getIndexableClassRepository()->findAll() as $class) {
            $report = $this->createReport(
                $class->getLabel() . ' (' . $class->getUri() . ')',
                $this->getIndexName($class->getUri()),
                $class->countInstances([], ['recursive' => true])
            );

            $mainReport->add($report);
        }

        $mainReport->add(
            $this->createReport(
                'Metadata',
                'property-list',
                $this->getClassUriRepository()->getTotal()
            )
        );

        return $mainReport;
    }

    private function getIndexName(string $classUri): ?string
    {
        return IndexerInterface::AVAILABLE_INDEXES[$classUri] ?? null;
    }

    private function getTotalResults(string $index): int
    {
        /** @var ElasticSearch $advancedSearch */
        $advancedSearch = $this->getSearchProxy()->getAdvancedSearch();

        $query = new Query($index);
        $query->addCondition('*');
        $query->setLimit(1);

        return $advancedSearch->search($query)->getTotalCount();
    }

    private function createReport(string $classPresentation, string $index, int $totalInDb): Report
    {
        $totalIndexed = $this->getTotalResults($index);
        $percentage = $totalIndexed === 0 || $totalInDb === 0 ? 0 : (float)min(round($totalIndexed / $totalInDb * 100, 2), 100);
        $missingIndex = $totalInDb - $totalIndexed;

        $report = Report::createInfo($classPresentation);
        $report->add(Report::createSuccess('Total in DB: ' . $totalInDb));
        $report->add(Report::createSuccess('Total indexed "' . $index . '": ' . $totalIndexed));
        $report->add(new Report($percentage < 100 ? Report::TYPE_ERROR : Report::TYPE_SUCCESS, 'Percentage indexed: ' . $percentage . '%'));
        $report->add(new Report($missingIndex > 0 ? Report::TYPE_ERROR : Report::TYPE_SUCCESS, 'Missing items: ' . $missingIndex));

        return $report;
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
}
