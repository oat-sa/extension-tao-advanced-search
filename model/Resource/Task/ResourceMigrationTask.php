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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Resource\Task;

use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\DeliveryResult\Factory\DeliveryResultFilterFactory;
use oat\taoAdvancedSearch\model\DeliveryResult\Normalizer\DeliveryResultNormalizer;
use oat\taoAdvancedSearch\model\Index\Service\AbstractIndexMigrationTask;
use oat\taoAdvancedSearch\model\Index\Service\SyncResultIndexer;
use oat\taoAdvancedSearch\model\Resource\Factory\ResourceResultFilterFactory;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceSearcher;
use oat\taoAdvancedSearch\model\Resource\Service\SyncResourceResultIndexer;

class ResourceMigrationTask extends AbstractIndexMigrationTask
{
    protected function getConfig(): array
    {
        return [
            self::OPTION_RESULT_SEARCHER => ResourceSearcher::class,
            self::OPTION_RESULT_FILTER_FACTORY => ResourceResultFilterFactory::class,
            self::OPTION_INDEXER => SyncResourceResultIndexer::class,
        ];
    }
}
