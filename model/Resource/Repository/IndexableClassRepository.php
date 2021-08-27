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

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\tao\elasticsearch\IndexerInterface;
use oat\tao\model\menu\MenuService;

class IndexableClassRepository extends ConfigurableService implements IndexableClassRepositoryInterface
{
    use OntologyAwareTrait;

    /** @var array */
    private $menuPerspectives;

    public function withMenuPerspectives(array $menuPerspectives): self
    {
        $this->menuPerspectives = $menuPerspectives;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        $classes = [];
        $perspectives = $this->menuPerspectives ?? MenuService::getAllPerspectives();

        foreach ($perspectives as $perspective) {
            foreach ($perspective->getChildren() as $structure) {
                foreach ($structure->getTrees() as $tree) {
                    $rootNode = $tree->get('rootNode');

                    if (empty($rootNode)) {
                        continue;
                    }

                    //@TODO Remove direct Elastic search dependency from here [tech.debt]
                    $indexName = IndexerInterface::AVAILABLE_INDEXES[$rootNode] ?? null;

                    if ($indexName === null) {
                        continue;
                    }

                    $classes[$rootNode] = $this->getClass($rootNode);
                }
            }
        }

        return array_values($classes);
    }

    /**
     * @inheritDoc
     */
    public function findAllUris(): array
    {
        $out = [];

        foreach ($this->findAll() as $class) {
            $out[] = $class->getUri();
        }

        return $out;
    }
}
