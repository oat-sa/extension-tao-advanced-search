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
use oat\tao\elasticsearch\Query;
use oat\tao\elasticsearch\SearchResult;
use oat\tao\model\search\SearchProxy;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class ResourceIndexGarbageCollector extends ScriptAction implements ServiceLocatorAwareInterface
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
            'limit' => [
                'prefix' => 'l',
                'longPrefix' => 'limit',
                'flag' => false,
                'description' => 'Search limit',
                'defaultValue' => 100,
            ],
            'offset' => [
                'prefix' => 'o',
                'longPrefix' => 'offset',
                'flag' => false,
                'description' => 'Search offset',
                'defaultValue' => 0,
            ],
            'index' => [
                'prefix' => 'i',
                'longPrefix' => 'index',
                'flag' => false,
                'description' => 'The index to clean',
                'required' => true,
            ],
        ];
    }

    protected function provideDescription(): string
    {
        return 'Clear invalid indexes';
    }

    /**
     * @inheritDoc
     */
    protected function run(): Report
    {
        $index = $this->getOption('index');
        $limit = (int)$this->getOption('limit');
        $offset = (int)$this->getOption('offset');
        $report = Report::createInfo(sprintf('Cleaning documents for index "%s"', $index));
        $searcher = $this->getSearch();
        $totalRemoved = 0;
        $urisToRemove = [];

        do {
            $removed = $this->incrementUrisToRemove($index, $offset, $limit, $urisToRemove);
            $offset += $limit;
        } while ($removed !== null);

        foreach ($urisToRemove as $uri) {
            if ($searcher->remove($uri)) {
                $totalRemoved++;

                continue;
            }

            $errorMessage = sprintf(
                'Error removing resource "%s" for index "%s" (offset:%s, limit:%s)',
                $uri,
                $index,
                $offset,
                $limit
            );

            $report->add(Report::createError($errorMessage));

            $this->logError($errorMessage);
        }

        $message = sprintf(
            'Total of %s document(s) removed from index %s',
            $totalRemoved,
            $index
        );

        $report->add(Report::createSuccess($message));

        $this->logInfo($message);

        return $report;
    }

    private function incrementUrisToRemove(string $index, int $offset, int $limit, array &$urisToRemove): ?array
    {
        $results = $this->search($index, $offset, $limit);

        if ($results->count() === 0) {
            return null;
        }

        foreach ($results as $result) {
            $resource = $this->getResource($result['id']);

            if (!$resource->exists() && !$resource->isClass()) {
                $urisToRemove[] = $result['id'];
            }
        }

        return $urisToRemove;
    }

    private function search(string $index, int $offset, int $limit): SearchResult
    {
        return $this->getSearch()->search(
            (new Query($index))
                ->setLimit($limit)
                ->addCondition('*')
                ->setOffset($offset)
        );
    }

    private function getSearch(): ElasticSearch
    {
        return $this->getSearchProxy()->getAdvancedSearch();
    }

    private function getSearchProxy(): SearchProxy
    {
        return $this->getServiceLocator()->get(SearchProxy::SERVICE_ID);
    }
}
