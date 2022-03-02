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

namespace oat\taoAdvancedSearch\model\Search\Action;

use common_report_Report as Report;
use Exception;
use oat\tao\model\search\index\IndexUpdaterInterface;
use oat\tao\model\search\SearchInterface as TaoSearchInterface;
use oat\tao\model\search\SearchProxy;
use oat\tao\model\search\strategy\GenerisSearch;
use oat\oatbox\extension\InstallAction;
use oat\tao\model\search\Search;
use oat\taoAdvancedSearch\model\Search\IndexUpdater;
use oat\taoAdvancedSearch\model\Search\OpenSearch;
use oat\taoAdvancedSearch\model\Search\SearchInterface;

class InitAdvancedSearch extends InstallAction
{
    /**
     * @return array
     */
    protected function getDefaultHost(): array
    {
        return [
            'http://localhost:9200'
        ];
    }

    /**
     * @return array
     */
    protected function getDefaultSettings(): array
    {
        return [
            'analysis' => [
                'filter' => [
                    'autocomplete_filter' => [
                        'type' => 'edge_ngram',
                        'min_gram' => 1,
                        'max_gram' => 100,
                    ]
                ],
                'analyzer' => [
                    'autocomplete' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => [
                            'lowercase',
                            'autocomplete_filter',
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * @param array $params
     * @return Report
     * @throws \common_Exception
     * @throws \oat\oatbox\service\exception\InvalidServiceManagerException
     * @throws Exception
     */
    public function __invoke($params): Report
    {
        $report = new Report(Report::TYPE_INFO, 'Started switch to advanced search');

        $config = [
            'hosts' => $this->getDefaultHost(),
            'settings' => $this->getDefaultSettings(),
            'indexes' => array_pop($params)
        ];

        if (count($params) > 0) {
            $config['hosts'] = [];
            $hosts = $this->parseConnectionParams($params);
            $config['hosts'][] = $hosts;
        }

        if (count($params) > 0) {
            $config['hosts'][0]['port'] = array_shift($params);
        }

        if (count($params) > 0) {
            $config['hosts'][0]['user'] = array_shift($params);
        }

        if (count($params) > 0) {
            $config['hosts'][0]['pass'] = array_shift($params);
        }

        /** @var TaoSearchInterface $oldSearchService */
        $oldSearchService = $this->getServiceLocator()->get(SearchProxy::SERVICE_ID);

        $currentAdvancedSearch = $oldSearchService instanceof SearchInterface
            ? $oldSearchService
            : $oldSearchService->getAdvancedSearch();

        if ($currentAdvancedSearch instanceof SearchInterface) {
            $oldSettings = $oldSearchService->getOptions();

            if (isset($oldSettings['settings'])) {
                $config['settings'] = $oldSettings['settings'];
            }
        }

        $config[GenerisSearch::class] = new GenerisSearch();

        try {
            $advancedSearch = $this->getAdvancedSearch($config);
            $advancedSearch->createIndexes();

            /** @var SearchProxy $search */
            $search = $this->getServiceManager()->get(SearchProxy::SERVICE_ID);

            if ($search instanceof GenerisSearch) {
                $this->getServiceManager()->register(Search::SERVICE_ID, $advancedSearch);
            }

            if ($search instanceof SearchProxy) {
                $search->withAdvancedSearch($advancedSearch);

                $this->getServiceManager()->register(SearchProxy::SERVICE_ID, $search);
            }

            $report->add(new Report(Report::TYPE_SUCCESS, __('Switched search service implementation to Advanced Search')));

            $this->getServiceManager()->register(IndexUpdaterInterface::SERVICE_ID, $this->getIndexUpdater($config));
        } catch (Exception $e) {
            $report->add(new Report(Report::TYPE_ERROR, 'Unable to create index: (' . get_class($e) . ')' . $e->getMessage()));
        }

        return $report;
    }

    private function getAdvancedSearch(array $config): SearchInterface
    {
        // @TODO Should come from a proxy...
        return new OpenSearch($config);
    }

    private function getIndexUpdater(array $config): IndexUpdater
    {
        return new IndexUpdater($config['hosts']);
    }

    private function parseConnectionParams(array &$params)
    {
        $parsed = parse_url(array_shift($params));
        if (is_array($params) && !isset($parsed['host'])) {
            $parsed['host'] = $parsed['path'] ?? null;
            unset($parsed['path']);
        }
        return $parsed;
    }
}
