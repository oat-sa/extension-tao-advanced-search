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

use ArrayIterator;
use Exception;
use oat\oatbox\service\ConfigurableService;
use oat\tao\elasticsearch\ElasticSearch;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilder;
use oat\tao\model\search\ResultSet;
use oat\tao\model\search\Search;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\tree\Tree;

class PropertyCachingService extends ConfigurableService
{
    public const PROPERTY_ITEM = 'property_item';
    public const PROPERTY_ASSEMBLED_DELIVERY = 'property_assembled_delivery';
    public const PROPERTY_GROUP = 'property_group';
    public const PROPERTY_TEST = 'property_test';

    private const CACHE_INDEX_MAP = [
        TaoOntology::CLASS_URI_ITEM => self::PROPERTY_ITEM,
        TaoOntology::CLASS_URI_ASSEMBLED_DELIVERY => self::PROPERTY_ASSEMBLED_DELIVERY,
        TaoOntology::CLASS_URI_GROUP => self::PROPERTY_GROUP,
        TaoOntology::CLASS_URI_TEST => self::PROPERTY_TEST,
    ];

    /**
     * @throws Exception
     */
    public function indexClassPropertyTree(string $uri, Tree $tree, bool $forceClear = true): bool
    {
        if (self::CACHE_INDEX_MAP[$uri] === null) {
            throw new Exception('Uri is not mapped to correct index');
        }

        try {
            $this->removeExistingIndexedCache($uri);
            $this->getElasticSearchIndexer()->index(
                $this->parseTreeToIndexDocumentIterator(self::CACHE_INDEX_MAP[$uri], $tree)
            );
            return true;
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    public function parseTreeToIndexDocumentIterator(string $index, Tree $tree): ArrayIterator
    {
        return new ArrayIterator(
            [
                $this->getIndexDocumentBuilder()->createDocumentFromArray(
                    $this->buildTreeArray($tree, $index)
                )
            ]
        );
    }

    public function getCachedTree(string $index): ResultSet
    {
        return $this->getSearch()->query('*', $index);
    }

    private function getSearch(): ElasticSearch
    {
        return $this->getServiceLocator()->get(ElasticSearch::SERVICE_ID);
    }

    private function getIndexDocumentBuilder(): IndexDocumentBuilder
    {
        return $this->getServiceLocator()->get(IndexDocumentBuilder::class);
    }

    private function getElasticSearchIndexer(): Search
    {
        return $this->getServiceLocator()->get(Search::class);
    }

    private function buildTreeArray(Tree $tree, string $index): array
    {
        return [
            'id' => $index,
            'body' => [
                'type' => $index,
                'tree' => $tree->toArray(),
            ]
        ];
    }

    /**
     * @throws Exception
     */
    private function removeExistingIndexedCache(string $uri): bool
    {
        if ($this->getCachedTree($uri)->count() > 0) {
            try {
                $this->getSearch()->remove(self::CACHE_INDEX_MAP[$uri]);
                return true;
            } catch (Exception $exception) {
                throw new Exception('Could not remove index with this URI');
            }
        }

        return false;
    }
}
