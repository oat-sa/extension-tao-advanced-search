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

use Exception;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;

class IndexCreator extends ScriptAction
{
    private const INDEX_FILES = 'indexFiles';

    protected function provideOptions()
    {
        return [];
    }

    protected function provideDescription()
    {
        return 'Creates indices at Elastic Search';
    }

    protected function run()
    {
        /** @var SearchProxy $searchProxy */
        $searchProxy = $this->getServiceManager()->get(SearchProxy::SERVICE_ID);

        /** @var ElasticSearch|null $elasticService */
        $elasticService = $searchProxy->getAdvancedSearch();

        if ($elasticService instanceof ElasticSearch) {
            try {
                $elasticService->createIndexes();
            } catch (Exception $exception) {
                return new Report(
                    Report::TYPE_ERROR,
                    sprintf(
                        'Error while indices creation: %s',
                        $exception->getMessage()
                    )
                );
            }

            return new Report(Report::TYPE_SUCCESS, 'Elastic indexes created successfully');
        }

        return new Report(Report::TYPE_ERROR, 'No proper service found');
    }
}
