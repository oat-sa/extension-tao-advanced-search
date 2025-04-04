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

namespace oat\taoAdvancedSearch\tests\Unit\Resource\Service;

use core_kernel_classes_Resource;
use oat\generis\model\data\Ontology;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\resources\relation\FindAllQuery;
use oat\tao\model\search\ResultSet;
use oat\taoAdvancedSearch\model\Resource\Service\ItemClassRelationService;
use oat\taoAdvancedSearch\model\SearchEngine\AggregationQuery;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Query;
use PHPUnit\Framework\TestCase;

class ItemClassRelationServiceTest extends TestCase
{
    public function setUp(): void
    {
        $this->elasticSearch = $this->createMock(ElasticSearch::class);
        $this->advancedSearchChecker = $this->createMock(AdvancedSearchChecker::class);
        $this->ontology = $this->createMock(Ontology::class);
        $this->subject = new ItemClassRelationService(
            $this->elasticSearch,
            $this->advancedSearchChecker,
            $this->ontology
        );
    }

    public function testFindRelations(): void
    {
        $this->advancedSearchChecker->method('isEnabled')->willReturn(true);
        $resource = $this->createMock(core_kernel_classes_Resource::class);
        $this->ontology
            ->expects(self::once())
            ->method('getClass')
            ->with('classIdUri')
            ->willReturn($resource);

        $this->ontology
            ->expects(self::once())
            ->method('getClass')
            ->with('classIdUri')
            ->willReturn($resource);

        $this->ontology
            ->expects(self::once())
            ->method('getResource')
            ->with('resource_id')
            ->willReturn($resource);

        $findAllQuery = new FindAllQuery(
            null,
            'classIdUri',
            'itemClass'
        );

        $resource->expects(self::once())
            ->method('getNestedResources')
            ->willReturn([
                [
                    'id' => 'class_id',
                    'isclass' => 1,
                    'level' => 1
                ],
                [
                    'id' => 'resource_id',
                    'isclass' => 0,
                    'level' => 1
                ]
            ]);

        $resource->expects(self::once())
            ->method('getLabel')
            ->willReturn('resource_label');

        $aggregationQuery = new AggregationQuery(
            new Query('tests'),
            [
                'matching_uris' => [
                    'terms' => [
                        'field' => 'item_uris',
                        'include' => ['resource_id']
                    ]
                ]
            ],
            [
                'item_uris' => ['resource_id']
            ]
        );

        $resultSet = new ResultSet(
            [
                'resource_id'
            ],
            1
        );

        $this->elasticSearch
            ->expects(self::once())
            ->method('aggregate')
            ->with($aggregationQuery)
        ->willReturn($resultSet);

        $this->subject->findRelations($findAllQuery);
    }
}
