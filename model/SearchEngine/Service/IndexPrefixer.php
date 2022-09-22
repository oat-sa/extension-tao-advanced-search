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

namespace oat\taoAdvancedSearch\model\SearchEngine\Service;

use InvalidArgumentException;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchConfig;

class IndexPrefixer
{
    private const VALID_PREFIX_REGEX = '/[^a-z0-9\-]/';

    /** @var ElasticSearchConfig */
    private $config;

    public function __construct(ElasticSearchConfig $config)
    {
        $this->config = $config;
    }

    public function validate(string $prefix): void
    {
        if (preg_replace(self::VALID_PREFIX_REGEX, '', $prefix) !== $prefix) {
            throw new InvalidArgumentException(
                sprintf(
                    'The index prefix %s does not follow the pattern %s',
                    $prefix,
                    self::VALID_PREFIX_REGEX
                )
            );
        }
    }

    public function prefix(string $indexName): string
    {
        if ($this->config->getIndexPrefix()) {
            return $this->config->getIndexPrefix() . '-' . $indexName;
        }

        return $indexName;
    }

    public function prefixAll(array $indexes): array
    {
        $prefixedIndexes = [];

        foreach ($indexes as $index) {
            $prefixedIndexes[] = $this->prefix($index);
        }

        return $prefixedIndexes;
    }
}
