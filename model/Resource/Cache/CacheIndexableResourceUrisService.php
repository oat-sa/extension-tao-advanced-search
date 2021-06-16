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

namespace oat\taoAdvancedSearch\model\Resource\Cache;

use core_kernel_classes_Resource;
use League\Flysystem\FilesystemInterface;
use oat\generis\model\kernel\persistence\smoothsql\search\ComplexSearchService;
use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\filesystem\FileSystemService;
use oat\oatbox\service\ConfigurableService;
use oat\search\base\ResultSetInterface;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepository;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepositoryInterface;
use SplFileInfo;

class CacheIndexableResourceUrisService extends ConfigurableService
{
    private const TOTAL = '/tmp/taoAdvancedSearchTotalResources.txt';
    private const RESOURCES = '/tmp/taoAdvancedSearchResources.txt';

    use OntologyAwareTrait;

    public function warmup(): void
    {
        $classes = $this->getIndexableClassRepository()->findAll();
        $total = 0;

        $resourceUrisPath = self::RESOURCES;
        $totalPath = self::TOTAL;

        if ($this->hasFile($resourceUrisPath)) {
            $this->deleteFile($resourceUrisPath);
        }

        if ($this->hasFile($totalPath)) {
            $this->deleteFile($totalPath);
        }

        $this->createFile($resourceUrisPath);
        $this->createFile($totalPath);

        foreach ($classes as $class) {
            $offset = 0;
            $limit = 100;

            do {
                $resources = $this->searchResults($class->getUri(), $offset, $limit);
                $hasResults = $resources->count() > 0;
                $offset += $limit;

                /** @var core_kernel_classes_Resource $resource */
                foreach ($resources as $resource) {
                    $total++;

                    $this->appendFile($resourceUrisPath, $resource->getUri() . PHP_EOL);
                }
            } while ($hasResults);
        }

        $this->appendFile($totalPath, (string)$total);
    }

    public function getTotal(): int
    {
        if (is_readable(self::TOTAL)) {
            return (int)file_get_contents(self::TOTAL);
        }

        return 0;
    }

    public function getStream(): ?SplFileInfo
    {
        if (is_readable(self::RESOURCES)) {
            return new SplFileInfo(self::RESOURCES);
        }

        return null;
    }

    private function searchResults(string $classUri, int $offset, int $limit): ResultSetInterface
    {
        $search = $this->getComplexSearchService();

        $queryBuilder = $search->query()
            ->setLimit($limit)
            ->setOffset($offset);

        $criteria = $search->searchType($queryBuilder, $classUri, true);

        $queryBuilder = $queryBuilder->setCriteria($criteria);

        return $search->getGateway()->search($queryBuilder);
    }

    private function hasFile(string $filePath): bool
    {
        return file_exists($filePath);
    }

    private function appendFile(string $filePath, string $data): bool
    {
        return boolval(file_put_contents($filePath, $data, FILE_APPEND));
    }

    private function createFile(string $filePath): bool
    {
        return boolval(file_put_contents($filePath, ''));
    }

    private function deleteFile(string $filePath): bool
    {
        return unlink($filePath);
    }

    private function getIndexableClassRepository(): IndexableClassRepositoryInterface
    {
        return $this->getServiceLocator()->get(IndexableClassRepository::class);
    }

    private function getComplexSearchService(): ComplexSearchService
    {
        return $this->getServiceLocator()->get(ComplexSearchService::SERVICE_ID);
    }
}
