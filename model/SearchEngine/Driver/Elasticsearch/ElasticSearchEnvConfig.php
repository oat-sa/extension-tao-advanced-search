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

class ElasticSearchEnvConfig implements ElasticSearchConfigInterface
{
    public const ENV_OPTION_INDEX_PREFIX = 'ELASTICSEARCH_PREFIX';
    public const ENV_OPTION_ELASTIC_CLOUD_ID = 'ELASTICSEARCH_CLOUD_ID';
    public const ENV_OPTION_ELASTIC_CLOUD_API_KEY_ID = 'ELASTICSEARCH_API_KEY_ID';
    public const ENV_OPTION_ELASTIC_CLOUD_API_KEY = 'ELASTICSEARCH_API_KEY';
    public const ENV_OPTION_HOSTS = 'ELASTICSEARCH_HOSTS';
    public const ENV_OPTION_USERNAME = 'ELASTICSEARCH_USERNAME';
    public const ENV_OPTION_PASSWORD = 'ELASTICSEARCH_PASSWORD';

    /** @var GetEnvConfigs */
    private $getEnvConfigs;

    public function __construct(GetEnvConfigs $getEnvConfigs)
    {
        $this->getEnvConfigs = $getEnvConfigs;
    }

    public function getHosts(): array
    {
        $envHosts = $this->getEnvConfigs->getEnvByKey(self::ENV_OPTION_HOSTS);

        if ($envHosts) {
            return explode(' ', $envHosts);
        }

        return [];
    }

    public function getUsername(): ?string
    {
        return $this->getEnvConfigs->getEnvByKey(self::ENV_OPTION_USERNAME) ?: null;
    }

    public function getPassword(): ?string
    {
        return $this->getEnvConfigs->getEnvByKey(self::ENV_OPTION_PASSWORD) ?: null;
    }

    public function getIndexPrefix(): ?string
    {
        return $this->getEnvConfigs->getEnvByKey(self::ENV_OPTION_INDEX_PREFIX) ?: null;
    }

    public function getElasticCloudId(): ?string
    {
        return $this->getEnvConfigs->getEnvByKey(self::ENV_OPTION_ELASTIC_CLOUD_ID) ?: null;
    }

    public function getElasticCloudApiKey(): ?string
    {
        return $this->getEnvConfigs->getEnvByKey(self::ENV_OPTION_ELASTIC_CLOUD_API_KEY) ?: null;
    }

    public function getElasticCloudApiKeyId(): ?string
    {
        return $this->getEnvConfigs->getEnvByKey(self::ENV_OPTION_ELASTIC_CLOUD_API_KEY_ID) ?: null;
    }
}
