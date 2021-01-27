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

namespace oat\taoAdvancedSearch\model\Cache;

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\taoAdvancedSearch\model\tree\ClassElement;
use oat\taoAdvancedSearch\model\tree\PropertyElement;
use oat\taoAdvancedSearch\model\tree\Tree;

class PropertyTreeGenerator extends ConfigurableService
{
    use OntologyAwareTrait;

    /** @var Tree */
    private $tree;

    private function generateClassPropertyTree(string $uri)
    {
        $classRoot = $this->getClass($uri);
        $this->tree = new Tree(
            new ClassElement(
                $uri,
                '',
                $this->getClassProperty($classRoot)
            )
        );

        foreach ($classRoot->getSubClasses(true) as $class) {
            $parentClass = $class->getParentClasses();
            $this->tree->add(
                new ClassElement(
                    $class->getUri(),
                    reset($parentClass)->getUri(),
                    $this->getClassProperty($class)
                )
            );
        };

        return $this->tree;
    }


    public function getClassPropertyTree(string $uri): Tree
    {
        return $this->generateClassPropertyTree($uri);
    }

    /**
     * @return PropertyElement[]
     */
    private function getClassProperty(\core_kernel_classes_Class $class): array
    {
        $properties = [];
        foreach ($class->getProperties() as $property) {
            $properties[] = [
                'propertyUri' => $property->getUri(),
                'propertyLabel' => $property->getLabel()
            ];
        };

        return $properties;
    }
}