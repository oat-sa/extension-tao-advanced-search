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

use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\search\SearchProxy;
use Throwable;

/**
 * php index.php 'oat\taoAdvancedSearch\scripts\tools\IndexDeleter'
 */
class IndexDeleter extends ScriptAction
{
    protected function provideUsage(): array
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Delete all the indexes. Useful to refresh install'
        ];
    }

    protected function provideOptions(): array
    {
        return [];
    }

    protected function provideDescription(): string
    {
        return 'Delete all the indexes';
    }

    /**
     * @inheritDoc
     */
    protected function run(): Report
    {
        $report = Report::createInfo('Deleting indexes...');

        try {
            /** @var SearchProxy $searchProxy */
            $searchProxy = $this->getServiceManager()->get(SearchProxy::SERVICE_ID);
            $response = $searchProxy->flush();

            $report->add(
                Report::createSuccess(
                    sprintf('Indexes cleared: %s', var_export($response, true))
                )
            );
        } catch (Throwable $exception) {
            $report->add(
                Report::createError(
                    sprintf(
                        'Error clearing indexes: %s - %s - %s',
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
