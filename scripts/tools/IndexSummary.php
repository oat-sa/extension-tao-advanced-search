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
use oat\taoAdvancedSearch\model\Index\Report\IndexSummarizer;
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
        $mainReport = Report::createInfo('Index vs Storage');

        foreach ($this->getIndexSummarizer()->summarize() as $data) {
            $totalIndexed = $data['totalIndexed'];
            $percentage = $data['percentageIndexed'];
            $missingIndex = $data['totalMissingIndexation'];

            $report = Report::createInfo($data['label']);
            $report->add(Report::createSuccess('Total in DB: ' . $data['totalInDb']));
            $report->add(Report::createSuccess('Total indexed "' . $data['index'] . '": ' . $totalIndexed));
            $report->add(
                new Report(
                    $this->getPercentageReportTypeBy($percentage, (int)$data['totalInDb']),
                    'Percentage indexed: ' . $percentage . '%'
                )
            );
            $report->add(
                new Report(
                    $missingIndex > 0 ? Report::TYPE_ERROR : Report::TYPE_SUCCESS,
                    'Missing items: ' . $missingIndex
                )
            );

            $mainReport->add($report);
        }

        return $mainReport;
    }

    private function getPercentageReportTypeBy(float $percentage, int $totalInDB): string
    {
        if ($totalInDB === 0) {
            return Report::TYPE_WARNING;
        }

        if ($percentage < 100) {
            return Report::TYPE_ERROR;
        }

        return Report::TYPE_SUCCESS;
    }

    private function getIndexSummarizer(): IndexSummarizer
    {
        return $this->getServiceLocator()->get(IndexSummarizer::class);
    }
}
