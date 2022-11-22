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
 * Copyright (c) 2021-2022 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\Index\Listener;

use ArrayIterator;
use oat\generis\model\data\event\ResourceUpdated;
use oat\oatbox\log\LoggerService;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\SearchInterface;
use oat\taoAdvancedSearch\model\Index\Handler\TestUpdatedHandler;
use oat\taoAdvancedSearch\model\Index\Service\ResourceReferencesService;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use core_kernel_classes_Resource;
use oat\taoTests\models\event\TestUpdatedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TestUpdatedHandlerTest extends TestCase
{
    /** @var TestUpdatedHandler */
    private $sut;

    /** @var IndexDocument|MockObject */
    private $document;

    /** @var ResourceUpdated|MockObject */
    private $event;

    /** @var SearchInterface|MockObject */
    private $search;

    /** @var SearchInterface|MockObject */
    private $indexDocumentBuilder;

    /** @var ResourceReferencesService|MockObject */
    private $referencesService;

    public function setUp(): void
    {
        $this->search = $this->createMock(SearchInterface::class);
        $this->document = $this->createMock(IndexDocument::class);
        $this->event = $this->createMock(TestUpdatedEvent::class);

        $this->indexDocumentBuilder = $this->createMock(
            IndexDocumentBuilderInterface::class
        );
        $this->referencesService = $this->createMock(
            ResourceReferencesService::class
        );

        $this->sut = new TestUpdatedHandler(
            $this->createMock(LoggerService::class),
            $this->indexDocumentBuilder,
            $this->search,
            $this->referencesService
        );
    }

    public function testRejectsUnsupportedEvents(): void
    {
        $this->expectException(UnsupportedEventException::class);

        $this->event = $this->createMock(ResourceUpdated::class);
        $this->sut->handle($this->event);
    }

    public function testHandleTestWithEmptyURI(): void
    {
        $this->event
            ->method('jsonSerialize')
            ->willReturn([
                'testUri' => ''
            ]);

        $this->indexDocumentBuilder
            ->expects($this->never())
            ->method('createDocumentFromResource');

        $this->expectException(RuntimeException::class);
        $this->sut->handle($this->event);
    }

    public function testHandleTest(): void
    {
        $this->event
            ->method('jsonSerialize')
            ->willReturn([
                'testUri' => 'http://test/uri'
            ]);

        $this->document
            ->method('getBody')
            ->willReturn([
                'type' => 'resource-type'
            ]);

        $this->document
              ->method('getId')
              ->willReturn('documentId');
        $this->document
              ->method('getIndexProperties')
              ->willReturn([]);
        $this->document
              ->method('getDynamicProperties')
              ->willReturn(new ArrayIterator([]));
        $this->document
              ->method('getAccessProperties')
              ->willReturn(new ArrayIterator([]));

        $this->indexDocumentBuilder
            ->expects($this->once())
            ->method('createDocumentFromResource')
            ->willReturnCallback(function ($resource) {
                if (!$resource instanceof core_kernel_classes_Resource) {
                    $this->fail('Unexpected non-resource parameter');
                }
                if ($resource->getUri() === 'http://test/uri') {
                    return $this->document;
                }

                $this->fail('Unexpected resource URI: ' . $resource->getUri());
            });

        $this->referencesService
            ->expects($this->once())
            ->method('getBodyWithReferences')
            ->with($this->anything(), $this->document)
            ->willReturn([
                'type' => 'resource-type',
                ResourceReferencesService::REFERENCES_KEY => [
                    'http://item/1',
                    'http://item/2',
                ]
            ]);

        $this->search
            ->expects($this->once())
            ->method('index')
            ->willReturnCallback(function (array $documents) {
                /** @var $documents IndexDocument[] */

                $this->assertCount(1, $documents);
                $this->assertEquals(
                    'resource-type',
                    $documents[0]->getBody()['type']
                );
                $this->assertEquals(
                    [
                        'http://item/1',
                        'http://item/2',
                    ],
                    $documents[0]->getBody()['referenced_resources']
                );

                return 1;
            });

        $this->sut->handle($this->event);
    }

    public function testHandleNonTest(): void
    {
        $this->expectException(RuntimeException::class);

        $this->event
            ->method('jsonSerialize')
            ->willReturn([]);

        $this->indexDocumentBuilder
            ->expects($this->never())
            ->method('createDocumentFromResource');

        $this->search
            ->expects($this->never())
            ->method('index');

        $this->sut->handle($this->event);
    }
}
