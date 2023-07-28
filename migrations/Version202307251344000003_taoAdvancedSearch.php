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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\migrations;

use Doctrine\DBAL\Schema\Schema;
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\scripts\tools\IndexMigration;

final class Version202307251344000003_taoAdvancedSearch extends AbstractMigration
{
    private const INDEX_UPDATE_BODY = '{"properties": {"test_qti_structure": {"type": "object", "enabled": false}}}';

    public function getDescription(): string
    {
        return sprintf(
            'Migrate index "%s" with "%s"',
            IndexerInterface::TESTS_INDEX,
            self::INDEX_UPDATE_BODY
        );
    }

    public function up(Schema $schema): void
    {
        $this->runAction(
            new IndexMigration(),
            [
                '-i',
                IndexerInterface::TESTS_INDEX,
                '-q',
                self::INDEX_UPDATE_BODY,
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
