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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\model\Metadata\Normalizer;

use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use oat\generis\model\data\event\ResourceCreated;
use oat\generis\test\MockObject;
use oat\generis\test\ServiceManagerMockTrait;
use PHPUnit\Framework\TestCase;
use oat\oatbox\event\Event;
use oat\taoAdvancedSearch\model\Index\Service\ResultIndexer;
use oat\taoAdvancedSearch\model\Metadata\Listener\MetadataInheritanceListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use oat\taoAdvancedSearch\model\Metadata\Normalizer\MetadataNormalizer;
use oat\taoAdvancedSearch\model\Resource\Service\SyncResourceResultIndexer;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface as SearchEngineIndexerInterface;
use RuntimeException;

class MetadataInheritanceListenerTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var MetadataInheritanceListener */
    private $subject;

    /** @var ResultIndexer|MockObject */
    private $resultIndexerMock;

    /** @var MetadataNormalizer|MockObject */
    private $metadataNormalizerMock;

    /** @var SyncResourceResultIndexer|MockObject */
    private $syncIndexerMock;

    /** @var ResourceCreated|MockObject */
    private $eventMock;

    public function setUp(): void
    {
        $this->subject = new MetadataInheritanceListener();
        $this->resultIndexerMock = $this->createMock(ResultIndexer::class);
        $this->metadataNormalizerMock = $this->createMock(MetadataNormalizer::class);
        $this->syncIndexerMock = $this->createMock(SyncResourceResultIndexer::class);
        $this->eventMock = $this->createMock(ResourceCreated::class);
        $this->subject->setServiceLocator(
            $this->getServiceManagerMock(
                [
                    ResultIndexer::class => $this->resultIndexerMock,
                    MetadataNormalizer::class => $this->metadataNormalizerMock,
                    SyncResourceResultIndexer::class => $this->syncIndexerMock
                ]
            )
        );
    }

    public function testListenThrowExceptionOnWrongEvent(): void
    {
        $this->expectException(UnsupportedEventException::class);
        $this->subject->listen($this->createMock(Event::class));
    }

    public function testListen()
    {
        $classMock = $this->createMock(core_kernel_classes_Class::class);

        $this->eventMock
            ->expects($this->atLeastOnce())
            ->method('getResource')
            ->willReturn($classMock);

        $this->resultIndexerMock
            ->expects($this->once())
            ->method('setNormalizer');

        $this->subject->listen(
            $this->eventMock
        );
    }

    public function testListenForNonClass()
    {
        $classMock = $this->createMock(core_kernel_classes_Resource::class);

        $this->eventMock
            ->expects($this->atLeastOnce())
            ->method('getResource')
            ->willReturn($classMock);

        $this->resultIndexerMock
            ->expects($this->never())
            ->method('setNormalizer');

        $this->subject->listen(
            $this->eventMock
        );
    }

    public function testListenSyncIndexesMediaResourceByClassEquality(): void
    {
        $resourceMock = $this->createMock(core_kernel_classes_Resource::class);
        $mediaClassMock = $this->createMock(core_kernel_classes_Class::class);
        $typeMock = $this->createMock(core_kernel_classes_Class::class);

        $this->eventMock
            ->method('getResource')
            ->willReturn($resourceMock);

        $resourceMock
            ->method('getTypes')
            ->willReturn([$typeMock]);

        $resourceMock
            ->method('getClass')
            ->with(SearchEngineIndexerInterface::MEDIA_CLASS_URI)
            ->willReturn($mediaClassMock);

        $typeMock
            ->method('equals')
            ->with($mediaClassMock)
            ->willReturn(true);

        $this->syncIndexerMock
            ->expects($this->once())
            ->method('addIndex')
            ->with($resourceMock);

        $this->subject->listen($this->eventMock);
    }

    public function testListenSyncIndexesMediaResourceBySubClass(): void
    {
        $resourceMock = $this->createMock(core_kernel_classes_Resource::class);
        $mediaClassMock = $this->createMock(core_kernel_classes_Class::class);
        $typeMock = $this->createMock(core_kernel_classes_Class::class);

        $this->eventMock
            ->method('getResource')
            ->willReturn($resourceMock);

        $resourceMock
            ->method('getTypes')
            ->willReturn([$typeMock]);

        $resourceMock
            ->method('getClass')
            ->with(SearchEngineIndexerInterface::MEDIA_CLASS_URI)
            ->willReturn($mediaClassMock);

        $typeMock
            ->method('equals')
            ->with($mediaClassMock)
            ->willReturn(false);

        $typeMock
            ->method('isSubClassOf')
            ->with($mediaClassMock)
            ->willReturn(true);

        $this->syncIndexerMock
            ->expects($this->once())
            ->method('addIndex')
            ->with($resourceMock);

        $this->subject->listen($this->eventMock);
    }

    public function testListenDoesNotSyncIndexNonMediaResource(): void
    {
        $resourceMock = $this->createMock(core_kernel_classes_Resource::class);
        $mediaClassMock = $this->createMock(core_kernel_classes_Class::class);
        $typeMock = $this->createMock(core_kernel_classes_Class::class);

        $this->eventMock
            ->method('getResource')
            ->willReturn($resourceMock);

        $resourceMock
            ->method('getTypes')
            ->willReturn([$typeMock]);

        $resourceMock
            ->method('getClass')
            ->with(SearchEngineIndexerInterface::MEDIA_CLASS_URI)
            ->willReturn($mediaClassMock);

        $typeMock
            ->method('equals')
            ->with($mediaClassMock)
            ->willReturn(false);

        $typeMock
            ->method('isSubClassOf')
            ->with($mediaClassMock)
            ->willReturn(false);

        $this->syncIndexerMock
            ->expects($this->never())
            ->method('addIndex');

        $this->subject->listen($this->eventMock);
    }

    public function testListenDoesNotSyncIndexResourceWithoutTypes(): void
    {
        $resourceMock = $this->createMock(core_kernel_classes_Resource::class);

        $this->eventMock
            ->method('getResource')
            ->willReturn($resourceMock);

        $resourceMock
            ->method('getTypes')
            ->willReturn([]);

        $this->syncIndexerMock
            ->expects($this->never())
            ->method('addIndex');

        $this->subject->listen($this->eventMock);
    }

    public function testListenDoesNotThrowWhenSyncIndexingFails(): void
    {
        $resourceMock = $this->createMock(core_kernel_classes_Resource::class);
        $mediaClassMock = $this->createMock(core_kernel_classes_Class::class);
        $typeMock = $this->createMock(core_kernel_classes_Class::class);

        $this->eventMock
            ->method('getResource')
            ->willReturn($resourceMock);

        $resourceMock
            ->method('getTypes')
            ->willReturn([$typeMock]);

        $resourceMock
            ->method('getClass')
            ->with(SearchEngineIndexerInterface::MEDIA_CLASS_URI)
            ->willReturn($mediaClassMock);

        $typeMock
            ->method('equals')
            ->with($mediaClassMock)
            ->willReturn(true);

        $this->syncIndexerMock
            ->expects($this->once())
            ->method('addIndex')
            ->with($resourceMock)
            ->willThrowException(new RuntimeException('Sync indexing failed'));

        $this->subject->listen($this->eventMock);
    }
}
