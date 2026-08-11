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
use oat\tao\scripts\tools\migrations\AbstractMigration;
use oat\taoAdvancedSearch\scripts\install\CreateItemCommentIndex;

/**
 * Authoring comments (NYSED-13): ensure shared resource-comments Elasticsearch index exists.
 *
 * phpcs:disable Squiz.Classes.ValidClassName
 */
final class Version202608041200001488_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shared resource-comments ES index (NYSED-13)';
    }

    public function up(Schema $schema): void
    {
        $this->runAction(new CreateItemCommentIndex());
    }

    public function down(Schema $schema): void
    {
        // Elasticsearch index provisioning is not automatically reversible.
        $this->throwIrreversibleMigrationException();
    }
}
