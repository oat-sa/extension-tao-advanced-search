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
 * Copyright (c) 2025 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\scripts\tools;

use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\taoAdvancedSearch\model\Index\Service\RecreatingIndexService;

class RecreateIndex extends ScriptAction
{
    protected function provideUsage(): array
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Type this option to see the parameters.'
        ];
    }

    protected function provideOptions()
    {
        return [
            'index' => [
                'prefix' => 'i',
                'longPrefix' => 'index',
                'required' => true,
                'description' => 'Index that has to be recreated'
            ]
        ];
    }

    protected function provideDescription()
    {
        return 'Recreate the index';
    }

    protected function run()
    {
        $index = $this->getOption('index');
        $report = new Report(Report::TYPE_INFO, sprintf('Recreating Index: %s', $index));
        $this->getServiceManager()->getContainer()->get(RecreatingIndexService::class)->recreate($index, $report);
        $this->propagate(new IndexResourcePopulator())([]);
        return $report;
    }
}
