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

namespace oat\taoAdvancedSearch\model\Metadata\Factory;

use core_kernel_classes_Class;
use oat\oatbox\service\ConfigurableService;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassCachedRepository;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepositoryInterface;

class ClassPathFactory extends ConfigurableService
{
    /** @var IndexableClassRepositoryInterface */
    private $indexableClassRepository;

    public function create(core_kernel_classes_Class $class): array
    {
        $path = [$class->getUri()];

        if ($this->isRootClass($class)) {
            return $path;
        }

        foreach ($class->getParentClasses(true) as $parentClass) {
            if ($this->isRootClass($parentClass)) {
                $path[] = $parentClass->getUri();

                break;
            }

            $path[] = $parentClass->getUri();
        }

        return $path;
    }

    private function isRootClass(core_kernel_classes_Class $class)
    {
        return in_array($class->getUri(), $this->getIndexableClassRepository()->findAllUris(), true);
    }

    private function getIndexableClassRepository(): IndexableClassRepositoryInterface
    {
        if ($this->indexableClassRepository === null) {
            $this->indexableClassRepository = $this->getServiceLocator()->get(IndexableClassCachedRepository::class);
        }

        return $this->indexableClassRepository;
    }
}
