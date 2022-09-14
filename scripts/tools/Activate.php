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

use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Exception;
use oat\generis\model\DependencyInjection\ServiceOptions;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\search\index\IndexUpdaterInterface;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\Metadata\Service\AdvancedSearchSearchSettingsService;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfig;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\IndexUpdater;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

/**
 * php index.php 'oat\taoAdvancedSearch\scripts\tools\Activate' --help
 */
class Activate extends ScriptAction implements ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    protected function provideUsage(): array
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Type this option to see the parameters.'
        ];
    }

    protected function provideDescription()
    {
        return 'Init ElasticSearch';
    }

    protected function provideOptions(): array
    {
        return [
            'host' => [
                'prefix' => 'h',
                'longPrefix' => 'host',
                'required' => false,
                'description' => 'ElasticSearch host',
            ],
            'port' => [
                'prefix' => 'p',
                'longPrefix' => 'port',
                'required' => false,
                'description' => 'ElasticSearch post',
            ],
            'user' => [
                'prefix' => 'u',
                'longPrefix' => 'user',
                'required' => false,
                'description' => 'ElasticSearch user',
            ],
            'pass' => [
                'prefix' => 'p',
                'longPrefix' => 'pass',
                'required' => false,
                'description' => 'ElasticSearch pass',
            ],
            'indexPrefix' => [
                'prefix' => 'i',
                'longPrefix' => 'indexPrefix',
                'required' => false,
                'description' => 'ElasticSearch indices indexPrefix',
            ],
            'elasticCloudId' => [
                'prefix' => 'ecid',
                'longPrefix' => 'elasticCloudId',
                'required' => false,
                'description' => 'ElasticCloud ID',
            ],
            'elasticCloudApiKeyId' => [
                'prefix' => 'eckid',
                'longPrefix' => 'elasticCloudApiKeyId',
                'required' => false,
                'description' => 'ElasticCloud API Key ID',
            ],
            'elasticCloudApiKey' => [
                'prefix' => 'eck',
                'longPrefix' => 'elasticCloudApiKey',
                'required' => false,
                'description' => 'ElasticCloud API Key',
            ],
        ];
    }

    protected function run(): Report
    {
        $report = new Report(Report::TYPE_INFO, 'Started switch to elastic search');

        try {
            $serviceManager = $this->getServiceManager();
            $searchProxy = $this->getSearchProxy();
            $serviceOptions = $this->getServiceOptions();

            if ($this->hasOption('host')) {
                $url = parse_url($this->getOption('host'));

                $serviceOptions->save(
                    ElasticSearchConfig::class,
                    ElasticSearchConfig::OPTION_HOSTS,
                    [
                        array_filter(
                            [
                                'scheme' => $url['scheme'] ?? 'https',
                                'host' => $url['host'],
                                'port' => (int)$this->getOption('port'),
                                'user' => $this->getOption('user'),
                                'pass' => $this->getOption('pass'),
                            ]
                        )
                    ]
                );
            }

            if ($this->hasOption('elasticCloudId')) {
                $serviceOptions->save(
                    ElasticSearchConfig::class,
                    ElasticSearchConfig::OPTION_ELASTIC_CLOUD_ID,
                    $this->getOption('elasticCloudId')
                );
            }

            if ($this->hasOption('elasticCloudApiKeyId')) {
                $serviceOptions->save(
                    ElasticSearchConfig::class,
                    ElasticSearchConfig::OPTION_ELASTIC_CLOUD_API_KEY_ID,
                    $this->getOption('elasticCloudApiKeyId')
                );
            }

            if ($this->hasOption('elasticCloudApiKey')) {
                $serviceOptions->save(
                    ElasticSearchConfig::class,
                    ElasticSearchConfig::OPTION_ELASTIC_CLOUD_API_KEY,
                    $this->getOption('elasticCloudApiKey')
                );
            }

            if ($this->hasOption('indexPrefix')) {
                $serviceOptions->save(
                    ElasticSearchConfig::class,
                    ElasticSearchConfig::OPTION_INDEX_PREFIX,
                    $this->getOption('indexPrefix')
                );
            }

            $searchProxy->setOption(SearchProxy::OPTION_SEARCH_SETTINGS_SERVICE, AdvancedSearchSearchSettingsService::class);
            $searchProxy->setOption(SearchProxy::OPTION_ADVANCED_SEARCH_CLASS, ElasticSearch::class);

            $serviceManager->register(ServiceOptions::SERVICE_ID, $serviceOptions);
            $serviceManager->register(SearchProxy::SERVICE_ID, $searchProxy);
            $serviceManager->register(IndexUpdaterInterface::SERVICE_ID, new IndexUpdater());

            $report->add(Report::createSuccess(__('Switched search service implementation to ElasticSearch')));
        } catch (BadRequest400Exception $e) {
            $report->add(Report::createError('Unable to create index: ' . $e->getMessage()));
        } catch (Exception $e) {
            $report->add(Report::createError('ElasticSearch server could not be found: ' . $e->getTraceAsString()));
        }

        return $report;
    }

    private function getSearchProxy(): SearchProxy
    {
        return $this->getServiceManager()->getContainer()->get(SearchProxy::SERVICE_ID);
    }

    private function getServiceOptions(): ServiceOptions
    {
        return $this->getServiceManager()->getContainer()->get(ServiceOptions::class);
    }
}
