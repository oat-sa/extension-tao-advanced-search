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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine\Driver\Elasticsearch;

use DG\BypassFinals;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Response\Elasticsearch as ResponseElasticsearch;
use Exception;
use oat\tao\model\search\ResultSet;
use oat\tao\model\search\strategy\GenerisSearch;
use oat\tao\model\search\SyntaxException;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\QueryBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Normalizer\SearchResultNormalizer;
use oat\taoAdvancedSearch\model\SearchEngine\Query;
use oat\taoAdvancedSearch\model\SearchEngine\SearchResult;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionObject;

class ElasticSearchTest extends TestCase
{
    /** @var ElasticSearch */
    private $sut;

    /** @var Client|MockObject */
    private $client;

    /** @var GenerisSearch|MockObject */
    private $generisSearch;

    /** @var QueryBuilder|MockObject */
    private $queryBuilder;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var IndexerInterface|MockObject */
    private $indexer;

    /** @var SearchResultNormalizer|MockObject */
    private $searchResultNormalizer;

    /** @var IndexPrefixer|MockObject */
    private $prefixer;

    protected function setUp(): void
    {
        BypassFinals::enable();
        parent::setUp();
        $this->generisSearch = $this->createMock(GenerisSearch::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->client = $this->createMock(Client::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->indexer = $this->createMock(IndexerInterface::class);
        $this->prefixer = $this->createMock(IndexPrefixer::class);
        $this->searchResultNormalizer = $this->createMock(SearchResultNormalizer::class);

        $this->sut = new ElasticSearch(
            $this->client,
            $this->queryBuilder,
            $this->indexer,
            $this->prefixer,
            $this->logger,
            $this->searchResultNormalizer
        );

        $this->sut->setIndexFile(__DIR__ . '/../../../../sample/testIndexes.conf.php');

        $this->prefixer
            ->expects($this->any())
            ->method('prefix')
            ->willReturnArgument(0);

        $this->searchResultNormalizer
            ->expects($this->any())
            ->method('normalizeByByResultSet')
            ->willReturnArgument(0);

        $reflection = new ReflectionObject($this->sut);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue(
            $this->sut,
            $this->client
        );
    }

    public function testSearch(): void
    {
        $query = [
            'index' => 'indexName',
            'body' => json_encode(
                [
                    'query' => [
                        'query_string' => [
                            'default_operator' => 'AND',
                            'query' => 'a:"b"'
                        ]
                    ],
                    'size' => 777,
                    'from' => 7,
                    'sort' => [],
                ]
            )
        ];

        $responseMock = $this->createMock(ResponseElasticsearch::class);
        $responseMock->method('asArray')
            ->willReturn([]);
        $this->client
            ->method('search')
            ->with($query)
            ->willReturn($responseMock);

        $query = (new Query('indexName'))
            ->setOffset(7)
            ->setLimit(777)
            ->addCondition('a:"b"');

        $this->assertEquals(new SearchResult([], 0), $this->sut->search($query));
    }

    public function testCountDocuments(): void
    {
        $this->client
            ->method('count')
            ->with(
                [
                    'index' => 'indexName',
                ]
            )
            ->willReturn(
                [
                    'count' => 777,
                ]
            );

        $this->assertEquals(777, $this->sut->countDocuments('indexName'));
    }

    public function testQueryCallElasticSearchCaseClassIsSupported(): void
    {
        $validType = 'http://www.tao.lu/Ontologies/TAOItem.rdf#Item';

        $this->generisSearch->expects($this->never())
            ->method('query');

        $this->mockDebugLogger();

        $documentUri = 'https://tao.docker.localhost/ontologies/tao.rdf#i5ef45f413088c8e7901a84708e84ec';

        $responseMock = $this->createMock(ResponseElasticsearch::class);
        $responseMock->method('asArray')
            ->willReturn([
                'hits' => [
                    'hits' => [
                        [
                            '_id' => $documentUri,
                            '_source' => [
                                'attr1' => 'attr1 Value',
                                'attr2' => 'attr2 Value',
                                'attr3' => 'attr3 Value',
                            ],
                        ]
                    ],
                    'total' => [
                        'value' => 1
                    ]
                ]
            ]);

        $this->client->expects($this->once())
            ->method('search')
            ->willReturn($responseMock);

        $resultSet = $this->sut->query('item', $validType);

        $this->assertInstanceOf(ResultSet::class, $resultSet);
        $this->assertCount(1, $resultSet->getArrayCopy());
        $this->assertCount(4, $resultSet->getArrayCopy()[0]);
    }

    public function testQueryCallElasticSearchGenericError(): void
    {
        $this->expectException(SyntaxException::class);
        $validType = 'http://www.tao.lu/Ontologies/TAOItem.rdf#Item';

        $this->generisSearch->expects($this->never())
            ->method('query');

        $this->mockDebugLogger();

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Elasticsearch: An unknown error occurred during search "internal error"');

        $documentUri = 'https://tao.docker.localhost/ontologies/tao.rdf#i5ef45f413088c8e7901a84708e84ec';

        $this->client->expects($this->once())
            ->method('search')
            ->willThrowException(new Exception('internal error'));

        $resultSet = $this->sut->query('item', $validType);
    }

    public function testQueryCallElasticSearch400Error(): void
    {
        $this->expectException(SyntaxException::class);
        $validType = 'http://www.tao.lu/Ontologies/TAOItem.rdf#Item';

        $this->generisSearch->expects($this->never())
            ->method('query');

        $this->mockDebugLogger();

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Elasticsearch: There is an error in your search query, system returned: ' .
                    'Error {"error":{"reason": "Error"}}');

        $documentUri = 'https://tao.docker.localhost/ontologies/tao.rdf#i5ef45f413088c8e7901a84708e84ec';

        $this->client->expects($this->once())
            ->method('search')
            ->willThrowException(new Exception('{"error":{"reason": "Error"}}', 400));

        $resultSet = $this->sut->query('item', $validType);
    }

    public function testCreateIndexesCallIndexCreationBasedOnIndexOption(): void
    {
        $indexMock = $this->createMock(Indices::class);

        $indexMock->expects($this->exactly(2))
            ->method('create')
            ->withConsecutive(
                [
                    [
                        'index' => 'items',
                        'body' => [
                            'mappings' => [
                                'properties' => [
                                    'class' => [
                                        'type' => 'text',
                                    ],
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    [
                        'index' => 'tests',
                        'body' => [
                            'mappings' => [
                                'properties' => [
                                    'use' => [
                                        'type' => 'keyword',
                                    ],
                                ]
                            ]
                        ]
                    ]
                ]
            );

        $this->client->expects($this->any())
            ->method('indices')
            ->willReturn($indexMock);

        $this->sut->createIndexes();
    }


    public function testPingTrue(): void
    {
        $responseMock = $this->createMock(ResponseElasticsearch::class);
        $responseMock->method('asBool')->willReturn(true);
        $this->client
            ->expects($this->once())
            ->method('ping')
            ->willReturn($responseMock);

        $this->assertEquals(true, $this->sut->ping());
    }

    public function testPingFalse(): void
    {
        $responseMock = $this->createMock(ResponseElasticsearch::class);
        $responseMock->method('asBool')->willReturn(false);
        $this->client
            ->expects($this->once())
            ->method('ping')
            ->willReturn($responseMock);

        $this->assertEquals(false, $this->sut->ping());
    }

    private function mockDebugLogger(): void
    {
        $query = [
            'index' => 'items',
            'size' => 10,
            'from' => 0,
            'client' =>
                [
                    'ignore' => 404,
                ],
            'body' => '{"query":{"query_string":{"default_operator":"AND","query":"(\\"item\\")"}},' .
                '"sort":{"_id":{"order":"DESC"}}}',
        ];

        $this->queryBuilder->expects($this->once())
            ->method('getSearchParams')
            ->willReturn($query);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Elasticsearch Query ' . json_encode($query));
    }
}
