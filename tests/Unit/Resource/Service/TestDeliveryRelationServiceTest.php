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

namespace oat\taoAdvancedSearch\tests\Unit\Resource\Service;

use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\resources\relation\FindAllQuery;
use oat\tao\model\resources\relation\ResourceRelation;
use oat\tao\model\resources\relation\ResourceRelationCollection;
use oat\tao\model\resources\relation\service\ResourceRelationServiceProxy;
use oat\taoAdvancedSearch\model\Resource\Service\TestDeliveryRelationService;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\SearchResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TestDeliveryRelationServiceTest extends TestCase
{
    private ElasticSearch $elasticSearch;
    private AdvancedSearchChecker $advancedSearchChecker;
    private ResourceRelationServiceProxy $resourceRelationServiceProxy;
    private TestDeliveryRelationService $subject;

    protected function setUp(): void
    {
        $this->elasticSearch = $this->createMock(ElasticSearch::class);
        $this->advancedSearchChecker = $this->createMock(AdvancedSearchChecker::class);
        $this->resourceRelationServiceProxy = $this->createMock(ResourceRelationServiceProxy::class);

        $this->subject = new TestDeliveryRelationService(
            $this->elasticSearch,
            $this->advancedSearchChecker,
            $this->resourceRelationServiceProxy
        );
    }

    public function testFindRelationsUsesElasticsearchWhenEnabled(): void
    {
        $query = new FindAllQuery('testUri', null, 'delivery');

        $this->advancedSearchChecker->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->elasticSearch->expects(self::once())
            ->method('query')
            ->with('test_uri:testUri', IndexerInterface::DELIVERIES_INDEX, 0, 100, 'label.raw')
            ->willReturn(
                new SearchResult(
                    [
                        [
                            'id' => 'deliveryUri',
                            'label' => ['Delivery Label'],
                        ],
                    ],
                    1
                )
            );

        $this->resourceRelationServiceProxy->expects(self::never())
            ->method('findRelations');

        $result = $this->subject->findRelations($query);
        $serialized = $result->jsonSerialize();

        self::assertCount(1, $serialized);
        /** @var ResourceRelation $first */
        $first = reset($serialized);
        self::assertSame('delivery', $first->getType());
        self::assertSame('deliveryUri', $first->getId());
        self::assertSame('Delivery Label', $first->getLabel());
    }

    public function testFindRelationsUsesRdfFallbackWhenAdvancedSearchDisabled(): void
    {
        $query = new FindAllQuery('testUri', null, 'delivery');
        $fallbackResult = new ResourceRelationCollection();

        $this->advancedSearchChecker->expects(self::once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->elasticSearch->expects(self::never())
            ->method('query');

        $this->resourceRelationServiceProxy->expects(self::once())
            ->method('findRelations')
            ->with(self::callback(static function (FindAllQuery $fallbackQuery): bool {
                return $fallbackQuery->getSourceId() === 'testUri'
                    && $fallbackQuery->getType() === 'delivery_rdf';
            }))
            ->willReturn($fallbackResult);

        self::assertSame($fallbackResult, $this->subject->findRelations($query));
    }

    public function testFindRelationsUsesRdfFallbackWhenElasticsearchFails(): void
    {
        $query = new FindAllQuery('testUri', null, 'delivery');
        $fallbackResult = new ResourceRelationCollection();

        $this->advancedSearchChecker->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->elasticSearch->expects(self::once())
            ->method('query')
            ->willThrowException(new RuntimeException('Elasticsearch unavailable'));

        $this->resourceRelationServiceProxy->expects(self::once())
            ->method('findRelations')
            ->with(self::callback(static function (FindAllQuery $fallbackQuery): bool {
                return $fallbackQuery->getSourceId() === 'testUri'
                    && $fallbackQuery->getType() === 'delivery_rdf';
            }))
            ->willReturn($fallbackResult);

        self::assertSame($fallbackResult, $this->subject->findRelations($query));
    }

    public function testFindRelationsDoesNotUseFallbackWhenElasticsearchReturnsEmptyResult(): void
    {
        $query = new FindAllQuery('testUri', null, 'delivery');

        $this->advancedSearchChecker->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->elasticSearch->expects(self::once())
            ->method('query')
            ->willReturn(new SearchResult([], 0));

        $this->resourceRelationServiceProxy->expects(self::never())
            ->method('findRelations');

        self::assertCount(0, $this->subject->findRelations($query)->jsonSerialize());
    }
}
