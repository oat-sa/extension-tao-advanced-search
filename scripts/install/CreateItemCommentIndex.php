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

namespace oat\taoAdvancedSearch\scripts\install;

use oat\oatbox\extension\InstallAction;
use oat\oatbox\reporting\Report;
use oat\taoAdvancedSearch\model\Comment\ItemCommentIndexManager;

/**
 * Ensures the shared resource-comments Elasticsearch index exists (DI wires the adapter).
 */
class CreateItemCommentIndex extends InstallAction
{
    public function __invoke($params = [])
    {
        /** @var ItemCommentIndexManager $indexManager */
        $indexManager = $this->getServiceManager()->getContainer()->get(ItemCommentIndexManager::class);
        $indexName = $indexManager->ensureIndexExists();

        return Report::createSuccess(
            sprintf('Resource comments Elasticsearch index "%s" is ready', $indexName)
        );
    }
}
