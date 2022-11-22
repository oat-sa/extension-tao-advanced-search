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

declare(strict_types = 1);

namespace oat\taoAdvancedSearch\tests\Unit\Index\Listener;

use oat\generis\model\data\event\ResourceDeleted;
use oat\generis\model\data\event\ResourceUpdated;
use oat\oatbox\log\LoggerService;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\SearchInterface;
use oat\taoAdvancedSearch\model\Index\Handler\ResourceUpdatedHandler;
use oat\taoAdvancedSearch\model\Index\Service\ResourceReferencesService;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use oat\taoItems\model\media\ItemMediaResolver;
use core_kernel_classes_Resource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ResourceUpdatedHandlerTest extends TestCase
{
    /** @var ResourceUpdatedHandler */
    private $sut;

    /** @var IndexDocument|MockObject */
    private $document;

    /** @var core_kernel_classes_Resource|MockObject  */
    private $resource;

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
        if (!class_exists(ItemMediaResolver::class)) {
            $this->markTestSkipped(
                sprintf(
                    '%s needs %s from MediaManager',
                    ResourceUpdatedHandler::class,
                    ItemMediaResolver::class
                )
            );
        }

        $this->resolver = $this->createMock(ItemMediaResolver::class);
        $this->search = $this->createMock(SearchInterface::class);
        $this->resource = $this->createMock(core_kernel_classes_Resource::class);
        $this->document = $this->createMock(IndexDocument::class);

        $this->indexDocumentBuilder = $this->createMock(
            IndexDocumentBuilderInterface::class
        );

        $this->referencesService = $this->createMock(
            ResourceReferencesService::class
        );

        $this->sut = new ResourceUpdatedHandler(
            $this->createMock(LoggerService::class),
            $this->indexDocumentBuilder,
            $this->search,
            $this->referencesService
        );

        $this->event = $this->createMock(ResourceUpdated::class);
        $this->event
            ->method('getResource')
            ->willReturn($this->resource);
    }

    public function testRejectsUnsupportedEvents(): void
    {
        $this->expectException(UnsupportedEventException::class);

        $this->event = $this->createMock(ResourceDeleted::class);
        $this->sut->handle($this->event);
    }

    public function testHandleItem(): void
    {
        $this->document
            ->method('getId')
            ->willReturn('documentId');

        $this->document
            ->method('getIndexProperties')
            ->willReturn([]);

        $this->document
            ->method('getDynamicProperties')
            ->willReturn(new \ArrayIterator([]));

        $this->document
            ->method('getAccessProperties')
            ->willReturn(new \ArrayIterator([]));

        $this->indexDocumentBuilder
            ->expects($this->once())
            ->method('createDocumentFromResource')
            ->with($this->resource)
            ->willReturn($this->document);

        $this->referencesService
            ->expects($this->once())
            ->method('getBodyWithReferences')
            ->with($this->resource, $this->document)
            ->willReturn([
                'type' => 'resource-type',
                ResourceReferencesService::REFERENCES_KEY => [
                    'http://resources/referenced/1',
                    'http://resources/referenced/2',
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
                        'http://resources/referenced/1',
                        'http://resources/referenced/2',
                    ],
                    $documents[0]->getBody()['referenced_resources']
                );

                return 1;
            });

        $this->sut->handle($this->event);
    }

    public function testHandleNonItem(): void
    {
        $this->document
            ->method('getBody')
            ->willReturn([
                'type' => 'resource-type'
            ]);

        $this->indexDocumentBuilder
            ->expects($this->once())
            ->method('createDocumentFromResource')
            ->with($this->resource)
            ->willReturn($this->document);

        $this->referencesService
            ->expects($this->once())
            ->method('getBodyWithReferences')
            ->with($this->resource, $this->document)
            ->willReturn([
                'type' => 'resource-type',
                ResourceReferencesService::REFERENCES_KEY => []
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
                $this->assertEmpty(
                    $documents[0]->getBody()['referenced_resources']
                );

                return 1;
            });

        $this->sut->handle($this->event);
    }
}
