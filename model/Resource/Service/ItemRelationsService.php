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
 * Copyright (c) 2025 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Resource\Service;

use oat\tao\model\resources\relation\FindAllQuery;
use oat\tao\model\resources\relation\ResourceRelation;
use oat\tao\model\resources\relation\ResourceRelationCollection;
use oat\tao\model\resources\relation\service\ResourceRelationServiceInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Service\ItemUsageService;

class ItemRelationsService implements ResourceRelationServiceInterface
{
    private ItemUsageService $itemUsageService;

    public function __construct(ItemUsageService $itemUsageService)
    {
        $this->itemUsageService = $itemUsageService;
    }

    public function findRelations(FindAllQuery $query): ResourceRelationCollection
    {
        $resourceRelationCollection = new ResourceRelationCollection();
        foreach ($this->itemUsageService->getItemTests([$query->getSourceId()]) as $itemUsage) {
            $label = $itemUsage['label'] ?? [];
            $resourceRelationCollection->add(new ResourceRelation(
                'test',
                $itemUsage['id'],
                reset($label)
            ));
        }

        return $resourceRelationCollection;
    }
}
