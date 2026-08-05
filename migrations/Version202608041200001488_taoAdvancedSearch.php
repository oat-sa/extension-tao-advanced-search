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
use oat\taoAdvancedSearch\scripts\install\CreateItemCommentIndex;
use Throwable;

/**
 * Ensures item-comments Elasticsearch index exists (DI wires the adapter).
 *
 * phpcs:disable Squiz.Classes.ValidClassName
 */
final class Version202608041200001488_taoAdvancedSearch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create item-comments ES index for Item Comments (NYSED-19)';
    }

    public function up(Schema $schema): void
    {
        try {
            $script = new CreateItemCommentIndex();
            $script->setServiceLocator($this->getServiceLocator());
            $report = $script([]);

            if ($report instanceof Report) {
                $this->addReport($report);
            } else {
                $this->addReport(
                    Report::createSuccess('Item comments Elasticsearch index ensured')
                );
            }
        } catch (Throwable $exception) {
            $this->addReport(
                Report::createError(
                    sprintf('Failed ensuring item comments Elasticsearch index: %s', $exception->getMessage())
                )
            );
            throw $exception;
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally left empty: index remains for forward compatibility.
    }
}
