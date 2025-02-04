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
use oat\tao\model\search\ResultSet;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Query;

class ItemRelationsService implements ResourceRelationServiceInterface
{
    private const TEST_INDEX = 'tests';
    private const TEST_RELATION = 'test';
    private const ITEM_URIS = 'item_uris';
    private ElasticSearch $elasticSearch;

    public function __construct(ElasticSearch $elasticSearch)
    {
        $this->elasticSearch = $elasticSearch;
    }

    public function findRelations(FindAllQuery $query): ResourceRelationCollection
    {
        $resourceRelationCollection = new ResourceRelationCollection();
        foreach ($this->getItemTests([$query->getSourceId()]) as $itemUsage) {
            $label = $itemUsage['label'] ?? [];
            $resourceRelationCollection->add(new ResourceRelation(
                self::TEST_RELATION,
                $itemUsage['id'],
                reset($label)
            ));
        }

        return $resourceRelationCollection;
    }

    private function getItemTests(array $itemUris): ResultSet
    {
        $query = new Query(self::TEST_INDEX);
        foreach ($itemUris as $itemUri) {
            $query->addCondition(sprintf('%s:"%s"', self::ITEM_URIS, $itemUri));
        }
        return $this->elasticSearch->search($query);
    }
}
