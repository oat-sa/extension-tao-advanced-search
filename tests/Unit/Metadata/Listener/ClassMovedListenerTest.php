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
use oat\generis\test\MockObject;
use oat\generis\test\ServiceManagerMockTrait;
use PHPUnit\Framework\TestCase;
use oat\oatbox\event\Event;
use oat\tao\model\event\ClassMovedEvent;
use oat\taoAdvancedSearch\model\Index\Service\ResultIndexer;
use oat\taoAdvancedSearch\model\Metadata\Listener\ClassMovedListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use oat\taoAdvancedSearch\model\Metadata\Normalizer\MetadataNormalizer;

class ClassMovedListenerTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var MetadataNormalizer */
    private $subject;

    /** @var ResultIndexer|MockObject */
    private $resultIndexer;

    /** @var MetadataNormalizer|MockObject */
    private $metadataNormalizer;

    /** @var ClassMovedEvent|MockObject */
    private $event;

    public function setUp(): void
    {
        $this->subject = new ClassMovedListener();
        $this->resultIndexer = $this->createMock(ResultIndexer::class);
        $this->metadataNormalizer = $this->createMock(MetadataNormalizer::class);
        $this->event = $this->createMock(ClassMovedEvent::class);
        $this->subject->setServiceLocator(
            $this->getServiceManagerMock(
                [
                    ResultIndexer::class => $this->resultIndexer,
                    MetadataNormalizer::class => $this->metadataNormalizer
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
        $class = $this->createMock(core_kernel_classes_Class::class);
        $subClass = $this->createMock(core_kernel_classes_Class::class);

        $class->method('getSubClasses')
            ->willReturn([$subClass]);

        $this->event
            ->method('getClass')
            ->willReturn( $class);

        $this->resultIndexer
            ->expects($this->once())
            ->method('setNormalizer');

        $this->resultIndexer
            ->expects($this->exactly(2))
            ->method('addIndex');

        $this->subject->listen($this->event);
    }
}
