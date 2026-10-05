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
use oat\tao\scripts\tools\MigrationAction;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\Resource\Task\ResourceMigrationTask;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\scripts\tools\IndexMigration;
use Throwable;

/**
 * Adds {@code search_tokens} on the assets index (root + nested {@code attributes}) for term/prefix queries.
 * {@see IndexMigration} / {@code createIndexes} alone do not alter mappings on existing indices; repopulate
 * media resources so documents include generated tokens.
 */
final class Version202610011845001488_taoAdvancedSearch extends AbstractMigration
{
    /**
     * Matches {@code taoAdvancedSearch/config/assets.conf.php} {@code search_tokens} fields.
     */
    private const SEARCH_TOKENS_MAPPING_BODY = '{"properties":{"search_tokens":{"type":"keyword","ignore_above":256},"attributes":{"type":"nested","properties":{"search_tokens":{"type":"keyword","ignore_above":256}}}}}';

    private const REINDEX_CHUNK_SIZE = 500;

    public function getDescription(): string
    {
        return sprintf(
            'Put Elasticsearch search_tokens mapping on index "%s" and reindex TAOMedia resources',
            IndexerInterface::ASSETS_INDEX
        );
    }

    public function up(Schema $schema): void
    {
        $this->addReport(
            Report::createInfo(
                sprintf('Updating search_tokens mapping for index "%s"', IndexerInterface::ASSETS_INDEX)
            )
        );

        try {
            $this->runAction(
                new IndexMigration(),
                [
                    '-i',
                    IndexerInterface::ASSETS_INDEX,
                    '-q',
                    self::SEARCH_TOKENS_MAPPING_BODY,
                ]
            );

            $this->addReport(
                Report::createSuccess(
                    sprintf('Updated search_tokens mapping for index "%s"', IndexerInterface::ASSETS_INDEX)
                )
            );
        } catch (Throwable $e) {
            $this->addReport(
                Report::createError(
                    sprintf(
                        'Failed updating search_tokens mapping for index "%s": %s',
                        IndexerInterface::ASSETS_INDEX,
                        $e->getMessage()
                    )
                )
            );
            throw $e;
        }

        $classUri = IndexerInterface::MEDIA_CLASS_URI;
        $customParameters = sprintf('start=0&classUri=%s', rawurlencode($classUri));

        $this->addReport(
            Report::createInfo(
                sprintf('Reindexing media resources (classUri=%s) into assets index', $classUri)
            )
        );

        try {
            $this->runAction(
                new MigrationAction(),
                [
                    '-c',
                    (string) self::REINDEX_CHUNK_SIZE,
                    '-cp',
                    $customParameters,
                    '-t',
                    ResourceMigrationTask::class,
                    '-rp',
                ]
            );

            $this->addReport(Report::createSuccess('Media assets reindex task completed'));
        } catch (Throwable $e) {
            $this->addReport(
                Report::createError(sprintf('Failed reindexing media assets: %s', $e->getMessage()))
            );
            throw $e;
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
