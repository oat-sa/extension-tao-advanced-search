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

namespace oat\taoAdvancedSearch\model\Index\Service;

use Elastic\Elasticsearch\Client;
use Exception;
use oat\oatbox\reporting\Report;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;

class RecreatingIndexService
{
    private const INDEX_CONFIG_FILE = __DIR__
    . DIRECTORY_SEPARATOR . '..'
    . DIRECTORY_SEPARATOR . '..'
    . DIRECTORY_SEPARATOR . '..'
    . DIRECTORY_SEPARATOR . 'config'
    . DIRECTORY_SEPARATOR . 'index.conf.php';

    private Client $client;
    private IndexPrefixer $indexPrefixer;
    private AdvancedSearchChecker $advancedSearchChecker;

    public function __construct(
        Client                $client,
        IndexPrefixer         $indexPrefixer,
        AdvancedSearchChecker $advancedSearchChecker
    )
    {
        $this->client = $client;
        $this->indexPrefixer = $indexPrefixer;
        $this->advancedSearchChecker = $advancedSearchChecker;
    }

    public function recreate(string $index, Report $report): Report
    {
        if (!$this->advancedSearchChecker->isEnabled()) {
            $report->add(Report::createWarning('Advanced search is disabled'));
            return $report;
        }

        try {
            $this->client->indices()->delete(
                [
                    'index' => $index,
                    'ignore_unavailable' => true
                ]
            );

            if (!is_readable(self::INDEX_CONFIG_FILE)) {
                $report->add(Report::createError('Index config not found'));
                return $report;
            }

            $indexes = require self::INDEX_CONFIG_FILE;

            if (!isset($indexes[$index])) {
                $report->add(Report::createError('Index not found'));
                return $report;
            }

            $this->client->indices()->create([
                'index' => $this->indexPrefixer->prefix($index),
                'body' => $indexes[$index]['body']
            ]);


        } catch (Exception $exception) {
            $report->add(
                Report::createError(sprintf('Error while deleting index: %s', $exception->getMessage()))
            );
            return $report;
        }

        $report->add(Report::createInfo(sprintf('Index %s recreated', $index)));
        return $report;
    }
}
