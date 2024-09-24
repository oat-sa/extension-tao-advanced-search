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
 * Copyright (c) 2024 (original work) Open Assessment Technologies SA;
 *
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch;

use oat\generis\model\DependencyInjection\ServiceOptionsInterface;

class ElasticSearchConfigFactory
{
    /** @var ServiceOptionsInterface */
    private $serviceOptions;

    /** @var ElasticSearchConfig */
    private $elasticSearchConfig;

    /** @var ElasticSearchEnvConfig */
    private $elasticSearchEnvConfig;

    public function __construct(
        ServiceOptionsInterface $serviceOptions,
        ElasticSearchConfig $elasticSearchConfig,
        ElasticSearchEnvConfig $elasticSearchEnvConfig
        )
    {
        $this->serviceOptions = $serviceOptions;
        $this->elasticSearchConfig = $elasticSearchConfig;
        $this->elasticSearchEnvConfig = $elasticSearchEnvConfig;
    }

    public function getConfig(): ElasticSearchConfigInterface
    {
        if (
            $this->serviceOptions->get(ElasticSearchConfig::class, ElasticSearchConfig::OPTION_HOSTS)
            || $this->serviceOptions->get(ElasticSearchConfig::class, ElasticSearchConfig::OPTION_ELASTIC_CLOUD_ID)
            ) {
            return $this->elasticSearchConfig;
        }

        return $this->elasticSearchEnvConfig;
    }
}