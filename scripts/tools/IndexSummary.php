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

use core_kernel_classes_Class;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\elasticsearch\ElasticSearch;
use oat\tao\elasticsearch\IndexerInterface;
use oat\tao\elasticsearch\Query;
use oat\tao\model\menu\MenuService;
use oat\tao\model\search\SearchProxy;
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
        $classes = $this->getIndexedClasses();

        $mainReport = Report::createInfo('Index vs Storage');

        /** @var SearchProxy $searchProxy */
        $searchProxy = $this->getServiceLocator()->get(SearchProxy::SERVICE_ID);

        /** @var ElasticSearch $advancedSearch */
        $advancedSearch = $searchProxy->getAdvancedSearch();

        foreach ($classes as $classData) {
            /** @var core_kernel_classes_Class $class */
            $class = $classData['class'];
            $classPresentation = $class->getLabel() . '(' . $class->getUri() . ')';
            $total = $class->countInstances([], ['recursive' => true]);

            $query = new Query($classData['index']);
            $query->addCondition('*');
            $query->setLimit(1);

            $result = $advancedSearch->search($query);

            $report = Report::createInfo($classPresentation);
            $report->add(Report::createInfo('Total in DB: ' . $total));
            $report->add(Report::createInfo('Total indexed "' . $classData['index'] . '": ' . $result->getTotalCount()));

            $mainReport->add($report);
        }

        return $mainReport;
    }

    private function getIndexedClasses(): array
    {
        $classes = [];

        foreach (MenuService::getAllPerspectives() as $perspective) {
            foreach ($perspective->getChildren() as $structure) {
                foreach ($structure->getTrees() as $tree) {
                    $rootNode = $tree->get('rootNode');
                    if (!empty($rootNode)) {
                        $indexName = IndexerInterface::AVAILABLE_INDEXES[$rootNode] ?? null;

                        if ($indexName !== null) {
                            $classes[$rootNode] = [
                                'index' => $indexName,
                                'class' => $this->getClass($rootNode),
                            ];

                            continue;
                        }
                    }
                }
            }
        }

        return array_values($classes);
    }
}
