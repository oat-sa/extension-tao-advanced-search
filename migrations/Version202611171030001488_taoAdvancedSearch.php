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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
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

/** Adds the asset file size mapping and reindexes media resources with their calculated sizes. */
final class Version202611171030001488_taoAdvancedSearch extends AbstractMigration
{
    private const SIZE_MAPPING_BODY = '{"properties":{"size":{"type":"long"}}}';

    private const REINDEX_CHUNK_SIZE = 500;

    public function getDescription(): string
    {
        return sprintf(
            'Put file size mapping on index "%s" and reindex TAOMedia resources',
            IndexerInterface::ASSETS_INDEX
        );
    }

    public function up(Schema $schema): void
    {
        try {
            $mappingReport = $this->propagate(new IndexMigration())(
                [
                    '-i',
                    IndexerInterface::ASSETS_INDEX,
                    '-q',
                    self::SIZE_MAPPING_BODY,
                ]
            );

            if ($mappingReport instanceof Report) {
                $this->addReport($mappingReport);

                if ($mappingReport->getType() === Report::TYPE_ERROR) {
                    throw new RuntimeException(
                        sprintf('Failed updating file size mapping for index "%s"', IndexerInterface::ASSETS_INDEX)
                    );
                }
            }

            $this->addReport(
                Report::createSuccess(
                    sprintf('Updated file size mapping for index "%s"', IndexerInterface::ASSETS_INDEX)
                )
            );
        } catch (Throwable $e) {
            $this->addReport(
                Report::createError(
                    sprintf(
                        'Failed updating file size mapping for index "%s": %s',
                        IndexerInterface::ASSETS_INDEX,
                        $e->getMessage()
                    )
                )
            );
            throw $e;
        }

        $customParameters = sprintf('start=0&classUri=%s', rawurlencode(IndexerInterface::MEDIA_CLASS_URI));

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
