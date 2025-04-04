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

use common_Logger;
use oat\generis\model\data\Ontology;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\resources\relation\FindAllQuery;
use oat\tao\model\resources\relation\ResourceRelation;
use oat\tao\model\resources\relation\ResourceRelationCollection;
use oat\tao\model\resources\relation\service\ResourceRelationServiceInterface;
use oat\taoAdvancedSearch\model\SearchEngine\AggregationQuery;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Query;

class ItemClassRelationService implements ResourceRelationServiceInterface
{
    private const TEST_INDEX = 'tests';
    private const ITEM_URIS = 'item_uris';
    private const MATCHING_URIS = 'matching_uris';
    private const ITEM_RELATION = 'item';
    private ElasticSearch $elasticSearch;
    private AdvancedSearchChecker $advancedSearchChecker;
    private Ontology $ontology;

    public function __construct(
        ElasticSearch $elasticSearch,
        AdvancedSearchChecker $advancedSearchChecker,
        Ontology $ontology
    ) {
        $this->elasticSearch = $elasticSearch;
        $this->advancedSearchChecker = $advancedSearchChecker;
        $this->ontology = $ontology;
    }

    public function findRelations(FindAllQuery $query): ResourceRelationCollection
    {
        $resourceRelationCollection = new ResourceRelationCollection();
        if (!$this->advancedSearchChecker->isEnabled()) {
            common_Logger::w('Advanced search is not enabled, skipping item relations search');
            return $resourceRelationCollection;
        }

        $itemUris = array_column(
            array_filter($this->ontology->getClass($query->getClassId())->getNestedResources(), function ($item) {
                return $item['isclass'] === 0;
            }),
            'id'
        );
        $query = new Query(self::TEST_INDEX);

        $aggregation = new AggregationQuery(
            $query,
            [
                self::MATCHING_URIS => [
                    'terms' => [
                        'field' => self::ITEM_URIS,
                        'include' => $itemUris,
                    ],
                ]
            ],
            [
                'item_uris' => $itemUris
            ]
        );

        foreach ($this->elasticSearch->aggregate($aggregation) as $item) {
            $resourceRelationCollection->add(
                new ResourceRelation(
                    self::ITEM_RELATION,
                    $item,
                    $this->ontology->getResource($item)->getLabel()
                )
            );
        }

        return $resourceRelationCollection;
    }
}
