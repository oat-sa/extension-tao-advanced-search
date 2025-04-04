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

use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\resources\relation\FindAllQuery;
use oat\tao\model\resources\relation\ResourceRelation;
use oat\tao\model\resources\relation\ResourceRelationCollection;
use oat\taoAdvancedSearch\model\Resource\Service\ItemRelationsService;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\SearchResult;
use PHPUnit\Framework\TestCase;

class ItemRelationsServiceTest extends TestCase
{
    public function setUp(): void
    {
        $this->elasticSearch = $this->createMock(ElasticSearch::class);
        $this->advancedSearchChecker = $this->createMock(AdvancedSearchChecker::class);
        $this->subject = new ItemRelationsService($this->elasticSearch, $this->advancedSearchChecker);
    }

    public function testFindRelationsForItem(): void
    {
        $query = new FindAllQuery('itemSourceId', null, 'test');
        $this->advancedSearchChecker->method('isEnabled')->willReturn(true);

        $resultSearch = new SearchResult(
            [
                [
                    'label' => ['some label'],
                    'id' => 'some_id'
                ]
            ],
            1
        );

        $this->elasticSearch->method('query')->willReturn($resultSearch);

        $result = $this->subject->findRelations($query);
        $resultContent = $result->jsonSerialize();
        /** @var ResourceRelation $firstResult */
        $firstResult = reset($resultContent);
        $this->assertEquals('some_id', $firstResult->getId());
    }

    public function testFindRelationsWhenAdvancedSearchDisabled(): void
    {
        $query = new FindAllQuery('sourceId', 'classId', 'test');
        $this->advancedSearchChecker->method('isEnabled')->willReturn(false);
        $result = $this->subject->findRelations($query);
        $this->assertInstanceOf(ResourceRelationCollection::class, $result);
        $this->assertEmpty($result->jsonSerialize());
    }
}
