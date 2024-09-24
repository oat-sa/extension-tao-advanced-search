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

namespace oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

class ElasticSearchClientFactory
{
    /** @var ElasticSearchConfigInterface */
    private $config;

    public function __construct(ElasticSearchConfigFactory $config)
    {
        $this->config = $config->getConfig();
    }

    public function create(): Client
    {
        $clientBuilder = ClientBuilder::create();

        if ($this->config->getElasticCloudId()) {
            return $clientBuilder
                ->setElasticCloudId($this->config->getElasticCloudId())
                ->setApiKey($this->config->getElasticCloudApiKey(), $this->config->getElasticCloudApiKeyId())
                ->build();
        }

        $clientBuilder->setHosts($this->config->getHosts());
        if ($this->config->getUsername() && $this->config->getPassword()) {
            $clientBuilder->setBasicAuthentication($this->config->getUsername(), $this->config->getPassword());
        }

        return $clientBuilder->build();
    }
}
