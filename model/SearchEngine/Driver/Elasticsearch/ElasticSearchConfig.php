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

use oat\generis\model\DependencyInjection\ServiceOptionsInterface;

class ElasticSearchConfig
{
    public const OPTION_INDEX_PREFIX = 'index_prefix';
    public const OPTION_ELASTIC_CLOUD_ID = 'elastic_cloud_id';
    public const OPTION_ELASTIC_CLOUD_API_KEY_ID = 'elastic_cloud_api_key_id';
    public const OPTION_ELASTIC_CLOUD_API_KEY = 'elastic_cloud_api_key';
    public const OPTION_HOSTS = 'hosts';
    public const OPTION_USERNAME = 'user';
    public const OPTION_PASSWORD = 'pass';

    public const ENV_OPTION_INDEX_PREFIX = 'ELASTICSEARCH_PREFIX';
    public const ENV_OPTION_ELASTIC_CLOUD_ID = 'ELASTICSEARCH_CLOUD_ID';
    public const ENV_OPTION_ELASTIC_CLOUD_API_KEY_ID = 'ELASTICSEARCH_API_KEY_ID';
    public const ENV_OPTION_ELASTIC_CLOUD_API_KEY = 'ELASTICSEARCH_API_KEY';
    public const ENV_OPTION_HOSTS = 'ELASTICSEARCH_HOSTS';
    public const ENV_OPTION_USERNAME = 'ELASTICSEARCH_USERNAME';
    public const ENV_OPTION_PASSWORD = 'ELASTICSEARCH_PASSWORD';

    /** @var ServiceOptionsInterface */
    private ServiceOptionsInterface $serviceOptions;

    public function __construct(ServiceOptionsInterface $serviceOptions)
    {
        $this->serviceOptions = $serviceOptions;
    }

    public function getHosts(): array
    {
        $hosts = [];
        if ($this->serviceOptions->get(self::class, self::OPTION_HOSTS)) {
            foreach ($this->serviceOptions->get(self::class, self::OPTION_HOSTS) as $host) {
                if (is_array($host) && isset($host['host'])) {
                    $hosts[] = ($host['scheme'] ?? 'http') . '://' . $host['host'] . ':' . ($host['port'] ?? 9200);
                } else {
                    $hosts[] = $host;
                }
            }
            return $hosts;
        }

        if (getenv(self::ENV_OPTION_HOSTS)) {
            return explode(' ', getenv(self::ENV_OPTION_HOSTS));
        }

        return $hosts;
    }

    public function getUsername(): ?string
    {
        if ($this->serviceOptions->get(self::class, self::OPTION_HOSTS)) {
            return $this->getFirstHost()[self::OPTION_USERNAME] ?? null;
        }
        return getenv(self::ENV_OPTION_USERNAME) ?? null;
    }

    public function getPassword(): ?string
    {
        if ($this->serviceOptions->get(self::class, self::OPTION_HOSTS)) {
            return $this->getFirstHost()[self::OPTION_PASSWORD] ?? null;
        }
        return getenv(self::ENV_OPTION_PASSWORD) ?? null;
    }

    public function getIndexPrefix(): ?string
    {
        $indexPrefix = $this->serviceOptions->get(self::class, self::OPTION_INDEX_PREFIX);
        return $indexPrefix ?? getenv(self::ENV_OPTION_INDEX_PREFIX);
    }

    public function getElasticCloudId(): ?string
    {
        $elasticCloudId = $this->serviceOptions->get(self::class, self::OPTION_ELASTIC_CLOUD_ID);
        return $elasticCloudId ?? getenv(self::ENV_OPTION_ELASTIC_CLOUD_ID);
    }

    public function getElasticCloudApiKey(): ?string
    {
        $elasticCloudApiKey = $this->serviceOptions->get(self::class, self::OPTION_ELASTIC_CLOUD_API_KEY);
        return $elasticCloudApiKey ?? getenv(self::ENV_OPTION_ELASTIC_CLOUD_API_KEY);
    }

    public function getElasticCloudApiKeyId(): ?string
    {
        $elasticCloudApiKeyId = $this->serviceOptions->get(self::class, self::OPTION_ELASTIC_CLOUD_API_KEY_ID);
        return $elasticCloudApiKeyId ?? getenv(self::ENV_OPTION_ELASTIC_CLOUD_API_KEY_ID);
    }

    private function getFirstHost(): ?array
    {
        if ($this->serviceOptions->get(self::class, self::OPTION_HOSTS)) {
            return current($this->serviceOptions->get(self::class, self::OPTION_HOSTS));
        }
        return [current($this->getHosts())];
    }
}
