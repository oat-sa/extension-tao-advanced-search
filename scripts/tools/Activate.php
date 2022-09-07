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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA;
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\scripts\tools;

use oat\oatbox\action\ActionService;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use Throwable;

/**
 * php index.php 'oat\taoAdvancedSearch\scripts\tools\Activate'
 */
class Activate extends ScriptAction
{
    protected function provideUsage(): array
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Activate advanced search'
        ];
    }

    protected function provideOptions(): array
    {
        return [];
    }

    protected function provideDescription(): string
    {
        return 'Activate advanced search';
    }

    /**
     * @inheritDoc
     */
    protected function run(): Report
    {
        $report = Report::createInfo('Activating advanced search...');

        try {
            $params = $argv; //FIXME get options... TAOROOT [HOST] [PORT] [LOGIN] [PASSWORD]

            $params[] = require(__DIR__ . '/../config/index.conf.php');

            $actionService = $this->getServiceManager()->get(ActionService::SERVICE_ID);
            $factory = $actionService->resolve(InitElasticSearch::class);
            $report = $factory->__invoke($params);

            return $report;
        } catch (Throwable $exception) {
            $report->add(
                Report::createError(
                    sprintf(
                        'Error Activating advanced search: %s - %s - %s',
                        get_class($exception),
                        $exception->getMessage(),
                        $exception->getTraceAsString()
                    )
                )
            );
        }

        return $report;
    }
}
