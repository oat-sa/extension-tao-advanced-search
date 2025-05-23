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
use InvalidArgumentException;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\featureFlag\FeatureFlagChecker;
use oat\tao\model\resources\relation\FindAllQuery;
use oat\tao\model\resources\relation\ResourceRelation;
use oat\tao\model\resources\relation\ResourceRelationCollection;
use oat\tao\model\resources\relation\service\ResourceRelationServiceInterface;
use oat\tao\model\search\ResultSet;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;

class ItemRelationsService implements ResourceRelationServiceInterface
{
    private const TEST_INDEX = 'tests';
    private const TEST_RELATION = 'test';
    private const ITEM_URIS = 'item_uris';

    public const FEATURE_FLAG_ITEM_RELATION_ON_DELETE = 'FEATURE_FLAG_ITEM_RELATION_ON_DELETE';
    private ElasticSearch $elasticSearch;
    private AdvancedSearchChecker $advancedSearchChecker;
    private FeatureFlagChecker $featureFlagChecker;

    public function __construct(
        ElasticSearch $elasticSearch,
        AdvancedSearchChecker $advancedSearchChecker,
        FeatureFlagChecker $featureFlagChecker
    ) {
        $this->elasticSearch = $elasticSearch;
        $this->advancedSearchChecker = $advancedSearchChecker;
        $this->featureFlagChecker = $featureFlagChecker;
    }

    public function findRelations(FindAllQuery $query): ResourceRelationCollection
    {
        $resourceRelationCollection = new ResourceRelationCollection();
        if (
            !$this->featureFlagChecker->isEnabled(self::FEATURE_FLAG_ITEM_RELATION_ON_DELETE) ||
            !$this->advancedSearchChecker->isEnabled()
        ) {
            common_Logger::w('Advanced search is not enabled, skipping item relations search');
            return $resourceRelationCollection;
        }

        foreach ($this->getTests($query) as $itemUsage) {
            $label = $itemUsage['label'] ?? [];
            $resourceRelationCollection->add(new ResourceRelation(
                self::TEST_RELATION,
                $itemUsage['id'],
                reset($label)
            ));
        }

        return $resourceRelationCollection;
    }

    private function searchTestWithItems(array $itemUris): ResultSet
    {
        foreach ($itemUris as $itemUri) {
            $conditions[] = (sprintf('%s:%s', self::ITEM_URIS, $itemUri));
        }

        return $this->elasticSearch->query(
            implode(' LOGIC_OR ', $conditions),
            self::TEST_INDEX,
            0,
            20,
            'label.raw'
        );
    }

    private function getTests(FindAllQuery $query): ResultSet
    {
        if ($query->getSourceId()) {
            return $this->searchTestWithItems([$query->getSourceId()]);
        }

        throw new InvalidArgumentException('Query must have either sourceId or classId');
    }
}
