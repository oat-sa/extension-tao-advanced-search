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
use RuntimeException;
use Throwable;

/**
 * Adds case-insensitive {@code sort} subfields to {@code label} and {@code location} on the assets index.
 * {@see IndexMigration} / {@code createIndexes} alone do not alter mappings on existing indices; repopulate
 * media resources so documents include values for the new sort fields.
 */
final class Version202610271342361488_taoAdvancedSearch extends AbstractMigration
{
    /**
     * Matches {@code taoAdvancedSearch/config/assets.conf.php} {@code label} / {@code location} sort fields.
     */
    private const SORT_MAPPING_BODY = '{"properties":{"label":{"type":"text","fields":{"raw":{"type":"keyword"},"sort":{"type":"keyword","normalizer":"lowercase"}}},"location":{"type":"text","fields":{"raw":{"type":"keyword"},"sort":{"type":"keyword","normalizer":"lowercase"}}},"resource_id":{"type":"keyword","ignore_above":1024}}}';

    private const REINDEX_CHUNK_SIZE = 500;

    public function getDescription(): string
    {
        return sprintf(
            'Put case-insensitive label/location sort mapping on index "%s" and reindex TAOMedia resources',
            IndexerInterface::ASSETS_INDEX
        );
    }

    public function up(Schema $schema): void
    {
        $this->addReport(
            Report::createInfo(
                sprintf('Updating label/location sort mapping for index "%s"', IndexerInterface::ASSETS_INDEX)
            )
        );

        try {
            $mappingReport = $this->propagate(new IndexMigration())(
                [
                    '-i',
                    IndexerInterface::ASSETS_INDEX,
                    '-q',
                    self::SORT_MAPPING_BODY,
                ]
            );

            if ($mappingReport instanceof Report) {
                $this->addReport($mappingReport);

                if ($mappingReport->getType() === Report::TYPE_ERROR) {
                    throw new RuntimeException(
                        sprintf(
                            'Failed updating label/location sort mapping for index "%s"; '
                            . 'the reindex is aborted, fix the mapping and re-run the migration',
                            IndexerInterface::ASSETS_INDEX
                        )
                    );
                }
            }

            $this->addReport(
                Report::createSuccess(
                    sprintf('Updated label/location sort mapping for index "%s"', IndexerInterface::ASSETS_INDEX)
                )
            );
        } catch (Throwable $e) {
            $this->addReport(
                Report::createError(
                    sprintf(
                        'Failed updating label/location sort mapping for index "%s": %s',
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
            $reindexReport = $this->propagate(new MigrationAction())(
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

            if (!$reindexReport instanceof \common_report_Report) {
                throw new RuntimeException('Media assets reindex action returned no report');
            }

            $this->addReport($reindexReport);

            if ($reindexReport->getType() === Report::TYPE_ERROR) {
                throw new RuntimeException('Failed to queue the media assets reindex');
            }

            $this->addReport(Report::createSuccess('Media assets reindex queued (task queue worker required)'));
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
