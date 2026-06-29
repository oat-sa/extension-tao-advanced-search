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
 * Foundation, Inc., 31 Milk St # 960789 Boston, MA 02196 USA
 *
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\oatbox\reporting\Report;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\scripts\tools\IndexMigration;
use Throwable;

/**
 * Adds nested `attributes` mapping (same shape as taoAdvancedSearch/config/*.conf.php) on all
 * resource indices that store custom metadata under ElasticSearchIndexer nested attributes:
 * {@code key}, {@code type}, {@code value}, and {@code raw_value} (human-readable parallel to {@code value}).
 *
 * If `attributes` already exists with an incompatible type, PUT mapping fails; recreate the index or reindex.
 */
final class Version202605011800001488_taoAdvancedSearch extends AbstractMigration
{
    /**
     * Matches {@code taoAdvancedSearch/config/*.conf.php} nested {@code attributes} definition.
     */
    private const ATTRIBUTES_MAPPING_BODY = '{"properties":{"attributes":{"type":"nested","properties":{"key":{"type":"keyword"},"type":{"type":"keyword"},"value":{"type":"text","fields":{"raw":{"type":"keyword"}}},"raw_value":{"type":"text","fields":{"raw":{"type":"keyword"}}}}}}}';

    private const INDEX_NAMES = [
        IndexerInterface::ITEMS_INDEX,
        IndexerInterface::TESTS_INDEX,
        IndexerInterface::DELIVERIES_INDEX,
        IndexerInterface::GROUPS_INDEX,
        IndexerInterface::ASSETS_INDEX,
        IndexerInterface::TEST_TAKERS_INDEX,
    ];

    public function getDescription(): string
    {
        return 'Put Elasticsearch nested mapping for custom metadata field `attributes` (key, type, value, raw_value) on resource indices (items, tests, deliveries, groups, assets, test-takers)';
    }

    public function up(Schema $schema): void
    {
        foreach (self::INDEX_NAMES as $indexName) {
            $this->addReport(Report::createInfo(sprintf('Updating nested attributes mapping for index "%s"', $indexName)));

            try {
                $this->runAction(
                    new IndexMigration(),
                    [
                        '-i',
                        $indexName,
                        '-q',
                        self::ATTRIBUTES_MAPPING_BODY,
                    ]
                );

                $this->addReport(Report::createSuccess(sprintf('Updated nested attributes mapping for index "%s"', $indexName)));
            } catch (Throwable $e) {
                $this->addReport(
                    Report::createError(
                        sprintf('Failed updating nested attributes mapping for index "%s": %s', $indexName, $e->getMessage())
                    )
                );
                throw $e;
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
