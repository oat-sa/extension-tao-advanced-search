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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Search\Action;

use common_report_Report as Report;
use Exception;
use oat\oatbox\extension\script\ScriptAction;
use oat\tao\model\search\SearchInterface as TaoSearchInterface;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\Search\SearchInterface;

class IndexCreator extends ScriptAction
{
    private const INDEX_FILES = 'indexFiles';

    protected function provideOptions()
    {
        return
            [
                self::INDEX_FILES => [
                    'prefix' => 'f',
                    'longPrefix' => self::INDEX_FILES,
                    'required' => true,
                    'description' => 'Absolute path to indices declaration.',
                ],
            ];
    }

    protected function provideDescription()
    {
        return 'Creates advanced search indices';
    }

    protected function run()
    {
        /** @var SearchProxy $searchProxy */
        $searchProxy = $this->getServiceLocator()->get(SearchProxy::SERVICE_ID);

        /** @var TaoSearchInterface|null $advancedSearch */
        $advancedSearch = $searchProxy->getAdvancedSearch();

        if ($advancedSearch instanceof SearchInterface) {
            $advancedSearch->setOption('indexFiles', $this->getOption(self::INDEX_FILES) ?? []);

            try {
                $advancedSearch->createIndexes();
            } catch (Exception $exception) {
                return new Report(
                    Report::TYPE_ERROR,
                    sprintf(
                        'Error while indices creation: %s',
                        $exception->getMessage()
                    )
                );
            }

            return new Report(Report::TYPE_SUCCESS, 'Search indexes created successfully');
        }

        return new Report(Report::TYPE_ERROR, 'No proper service found');
    }
}
