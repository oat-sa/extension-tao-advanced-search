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

namespace oat\taoAdvancedSearch\model\Metadata\Repository;

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\cache\SimpleCache;
use oat\oatbox\service\ConfigurableService;
use Psr\SimpleCache\CacheInterface;

class ClassUriCachedRepository extends ConfigurableService implements ClassUriRepositoryInterface
{
    use OntologyAwareTrait;

    private const CLASSES_INDEX = self::class . '::CLASSES_URIS';

    private const CLASSES_INDEX_TOTAL = self::class . '::CLASSES_URIS_TOTAL';

    public function cacheWarmup(): void
    {
        $cacheService = $this->getCacheService();
        $cacheService->delete(self::CLASSES_INDEX);
        $cacheService->delete(self::CLASSES_INDEX_TOTAL);

        $this->getTotal();
    }

    public function findAll(): array
    {
        $cacheService = $this->getCacheService();

        if ($cacheService->has(self::CLASSES_INDEX)) {
            return (array)$cacheService->get(self::CLASSES_INDEX);
        }

        $allClassesUris = $this->getClassUriRepository()->findAll();

        $cacheService->set(self::CLASSES_INDEX, $allClassesUris);
        $cacheService->set(self::CLASSES_INDEX_TOTAL, count($allClassesUris));

        return $allClassesUris;
    }

    public function getTotal(): int
    {
        $cacheService = $this->getCacheService();

        if ($cacheService->has(self::CLASSES_INDEX_TOTAL)) {
            return (int)$cacheService->get(self::CLASSES_INDEX_TOTAL);
        }

        return count($this->findAll());
    }

    private function getCacheService(): CacheInterface
    {
        return $this->getServiceLocator()->get(SimpleCache::SERVICE_ID);
    }

    private function getClassUriRepository(): ClassUriRepositoryInterface
    {
        return $this->getServiceLocator()->get(ClassUriRepository::class);
    }
}
