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

namespace oat\taoAdvancedSearch\model\Metadata\Normalizer;

use core_kernel_classes_Class;
use InvalidArgumentException;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\Lists\Business\Domain\Metadata;
use oat\tao\model\Lists\Business\Service\GetClassMetadataValuesService;
use oat\taoAdvancedSearch\model\Index\IndexResource;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;
use oat\taoAdvancedSearch\model\Metadata\Service\MetadataResultSearcher;

class MetadataNormalizer extends ConfigurableService implements NormalizerInterface
{
    public function normalize($class): IndexResource
    {
        if (!$class instanceof core_kernel_classes_Class) {
            throw new InvalidArgumentException(
                '$class must be an instance of ' . core_kernel_classes_Class::class
            );
        }

        return new IndexResource(
            $class->getUri(),
            $class->getLabel(),
            [
                'type' => 'property-list',
                'parentClass' => $this->getParentClass($class),
                'propertiesTree' => $this->getPropertiesFromClass($class),
            ]
        );
    }

    private function getParentClass(core_kernel_classes_Class $class): ?string
    {
        if ($this->isRootClass($class)) {
            return null;
        }

        $parentClass = $class->getParentClasses();
        return reset($parentClass)->getUri();
    }

    private function getPropertiesFromClass(core_kernel_classes_Class $class): array
    {
        $propertyCollection = [];
        $properties = $this->isRootClass($class)
            ? $this->getGetClassMetadataValuesService()->getByClass($class)
            : $this->getGetClassMetadataValuesService()->getByClassExplicitly($class);

        /** @var Metadata $property */
        foreach ($properties as $property) {
            $propertyCollection[] = [
                'propertyUri' => $property->getPropertyUri(),
                'propertyLabel' => $property->getLabel(),
                'propertyType' => $property->getType(),
                'propertyValues' => $property->getUri()
                    ? null
                    : $property->getValues(),
            ];
        }

        return $propertyCollection;
    }

    private function getGetClassMetadataValuesService(): GetClassMetadataValuesService
    {
        return $this->getServiceLocator()->get(GetClassMetadataValuesService::class);
    }

    private function isRootClass(core_kernel_classes_Class $class)
    {
        return in_array($class->getUri(), MetadataResultSearcher::ROOT_CLASSES, true);
    }
}