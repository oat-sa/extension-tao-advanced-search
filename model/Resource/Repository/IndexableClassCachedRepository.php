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

namespace oat\taoAdvancedSearch\model\Resource\Repository;

use oat\oatbox\cache\SimpleCache;
use oat\oatbox\service\ConfigurableService;
use Psr\SimpleCache\CacheInterface;

class IndexableClassCachedRepository extends ConfigurableService implements IndexableClassRepositoryInterface
{
    private const CLASSES_INDEX_URIS = self::class . '::CLASSES_URIS';

    public function cacheWarmup(): void
    {
        $cacheService = $this->getCacheService();
        $cacheService->delete(self::CLASSES_INDEX_URIS);

        $this->findAllUris();
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        return $this->getIndexableClassRepository()->findAll();
    }

    /**
     * @inheritDoc
     */
    public function findAllUris(): array
    {
        $cacheService = $this->getCacheService();

        if ($cacheService->has(self::CLASSES_INDEX_URIS)) {
            return $cacheService->get(self::CLASSES_INDEX_URIS);
        }

        $uris = $this->getIndexableClassRepository()->findAllUris();

        $cacheService->set(self::CLASSES_INDEX_URIS, $uris);

        return $uris;
    }

    private function getCacheService(): CacheInterface
    {
        return $this->getServiceLocator()->get(SimpleCache::SERVICE_ID);
    }

    private function getIndexableClassRepository(): IndexableClassRepositoryInterface
    {
        return $this->getServiceLocator()->get(IndexableClassRepository::class);
    }
}
