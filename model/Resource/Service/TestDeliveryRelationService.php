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
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Resource\Service;

use common_Logger;
use InvalidArgumentException;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\resources\relation\FindAllQuery;
use oat\tao\model\resources\relation\ResourceRelation;
use oat\tao\model\resources\relation\ResourceRelationCollection;
use oat\tao\model\resources\relation\service\ResourceRelationServiceInterface;
use oat\tao\model\resources\relation\service\ResourceRelationServiceProxy;
use oat\tao\model\search\ResultSet;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use Throwable;

class TestDeliveryRelationService implements ResourceRelationServiceInterface
{
    private const DELIVERY_RELATION = 'delivery';
    private const FALLBACK_RELATION_TYPE = 'delivery_rdf';
    private const TEST_URI_FIELD = 'test_uri';

    private ElasticSearch $elasticSearch;
    private AdvancedSearchChecker $advancedSearchChecker;
    private ResourceRelationServiceProxy $resourceRelationServiceProxy;

    public function __construct(
        ElasticSearch $elasticSearch,
        AdvancedSearchChecker $advancedSearchChecker,
        ResourceRelationServiceProxy $resourceRelationServiceProxy
    ) {
        $this->elasticSearch = $elasticSearch;
        $this->advancedSearchChecker = $advancedSearchChecker;
        $this->resourceRelationServiceProxy = $resourceRelationServiceProxy;
    }

    public function findRelations(FindAllQuery $query): ResourceRelationCollection
    {
        if (!$query->getSourceId()) {
            throw new InvalidArgumentException('Query must have sourceId to resolve deliveries');
        }

        if (!$this->advancedSearchChecker->isEnabled()) {
            return $this->findRdfRelations($query);
        }

        try {
            return $this->findElasticsearchRelations($query->getSourceId());
        } catch (Throwable $exception) {
            common_Logger::w(
                sprintf(
                    'Unable to load test->delivery relations from Elasticsearch, fallback to RDF. Error: %s',
                    $exception->getMessage()
                )
            );

            return $this->findRdfRelations($query);
        }
    }

    private function findElasticsearchRelations(string $testUri): ResourceRelationCollection
    {
        $relations = [];

        foreach ($this->searchDeliveriesByTestUri($testUri) as $delivery) {
            $labels = $delivery['label'] ?? [];
            $relations[] = new ResourceRelation(
                self::DELIVERY_RELATION,
                $delivery['id'],
                reset($labels)
            );
        }

        return new ResourceRelationCollection(...$relations);
    }

    private function searchDeliveriesByTestUri(string $testUri): ResultSet
    {
        return $this->elasticSearch->query(
            sprintf('%s:%s', self::TEST_URI_FIELD, $testUri),
            IndexerInterface::DELIVERIES_INDEX,
            0,
            100,
            'label.raw'
        );
    }

    private function findRdfRelations(FindAllQuery $query): ResourceRelationCollection
    {
        return $this->resourceRelationServiceProxy->findRelations(
            new FindAllQuery(
                $query->getSourceId(),
                $query->getClassId(),
                self::FALLBACK_RELATION_TYPE
            )
        );
    }
}
