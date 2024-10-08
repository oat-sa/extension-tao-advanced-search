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

use oat\oatbox\log\LoggerService;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\TaoOntology;
use Elastic\Elasticsearch\Client;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearchIndexer;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use PHPUnit\Framework\MockObject\MockObject;
use ArrayIterator;
use DG\BypassFinals;
use PHPUnit\Framework\TestCase;

class ElasticSearchIndexerTest extends TestCase
{
    /** @var Client|MockObject */
    private $client;

    /** @var LoggerService|MockObject */
    private $logger;

    /** @var ElasticSearchIndexer $sut */
    private $sut;

    /** @var IndexPrefixer|MockObject */
    private $prefixer;

    protected function setUp(): void
    {
        BypassFinals::enable();
        $this->client = $this->createMock(Client::class);
        $this->logger = $this->createMock(LoggerService::class);
        $this->prefixer = $this->createMock(IndexPrefixer::class);

        $this->prefixer
            ->expects($this->any())
            ->method('prefix')
            ->willReturnArgument(0);

        $this->sut = new ElasticSearchIndexer(
            $this->client,
            $this->logger,
            $this->prefixer
        );
    }

    public function testGetIndexNameByDocument(): void
    {
        $document = $this->createMock(IndexDocument::class);
        $document->expects($this->once())
            ->method('getBody')
            ->willReturn([
                'type' => [
                    TaoOntology::CLASS_URI_ITEM
                ]
            ]);

        $indexName = $this->sut->getIndexNameByDocument($document);

        $this->assertSame(IndexerInterface::ITEMS_INDEX, $indexName);
    }

    public function testGetIndexNameByDocumentForUnclassifieds(): void
    {
        $document = $this->createMock(IndexDocument::class);
        $document->expects($this->once())
            ->method('getBody')
            ->willReturn([
                'type' => [
                    'Some_Unclassified'
                ]
            ]);

        $indexName = $this->sut->getIndexNameByDocument($document);

        $this->assertSame(IndexerInterface::UNCLASSIFIEDS_DOCUMENTS_INDEX, $indexName);
    }

    public function testBuildIndex(): void
    {
        // Mock the IndexDocument
        $document = $this->createMock(IndexDocument::class);
        $document->expects($this->any())
            ->method('getBody')
            ->willReturn([
                'type' => [
                    TaoOntology::CLASS_URI_ITEM
                ]
            ]);
        $document->expects($this->any())
            ->method('getId')
            ->willReturn('some_id');

        $this->logger->expects($this->exactly(1)) // 3 logs: info + debug + debug
        ->method('info')
            ->with('[documentId: "some_id"] Queuing document with types ' .
                'http://www.tao.lu/Ontologies/TAOItem.rdf#Item ' .
                sprintf('into index "%s"', IndexerInterface::ITEMS_INDEX));

        $this->logger->expects($this->exactly(2))
            ->method('debug')
            ->withConsecutive(
                [
                    ElasticSearchIndexer::class . '::buildIndex' .
                    ': Flushing batch with 1 operations'
                ],
                ['Processed 1 items (no exceptions, no skipped items)']
            );

        /** @var ArrayIterator|MockObject $iterator */
        $iterator = $this->createIterator([$document]);
        $iterator->expects($this->once())
            ->method('next');

        $this->client->expects($this->atLeastOnce())
            ->method('bulk')
            ->with([
                'body' => [
                    ['index' => [
                        '_index' => IndexerInterface::ITEMS_INDEX,
                        '_id' => 'some_id'
                    ]],
                    ['type' => [
                        TaoOntology::CLASS_URI_ITEM
                    ]],
                ]
            ])
            ->willReturn(['bulk_response']);

        $count = $this->sut->buildIndex($iterator);

        $this->assertSame(1, $count);
    }

    private function createIterator(array $items = []): MockObject
    {
        $iteratorMock = $this->createMock(ArrayIterator::class);

        $iterator = new ArrayIterator($items);

        $iteratorMock
            ->method('rewind')
            ->willReturnCallback(function () use ($iterator): void {
                $iterator->rewind();
            });

        $iteratorMock
            ->method('current')
            ->willReturnCallback(function () use ($iterator) {
                return $iterator->current();
            });

        $iteratorMock
            ->method('key')
            ->willReturnCallback(function () use ($iterator) {
                return $iterator->key();
            });

        $iteratorMock
            ->method('next')
            ->willReturnCallback(function () use ($iterator): void {
                $iterator->next();
            });

        $iteratorMock
            ->method('valid')
            ->willReturnCallback(function () use ($iterator): bool {
                return $iterator->valid();
            });

        return $iteratorMock;
    }
}
