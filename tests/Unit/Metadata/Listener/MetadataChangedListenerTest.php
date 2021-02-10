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
use oat\generis\test\TestCase;
use oat\oatbox\event\Event;
use oat\tao\model\event\ClassPropertiesChangedEvent;
use oat\taoAdvancedSearch\model\Index\Service\ResultIndexer;
use oat\taoAdvancedSearch\model\Metadata\Listener\MetadataChangedListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use oat\taoAdvancedSearch\model\Metadata\Normalizer\MetadataNormalizer;

class MetadataChangedListenerTest extends TestCase
{
    /** @var MetadataNormalizer */
    private $subject;

    /** @var ResultIndexer|MockObject */
    private $resultIndexerMock;

    /** @var MetadataNormalizer|MockObject */
    private $metadataNormalizerMock;

    /** @var ClassPropertiesChangedEvent|MockObject */
    private $eventMock;

    public function setUp(): void
    {
        $this->subject = new MetadataChangedListener();
        $this->resultIndexerMock = $this->createMock(ResultIndexer::class);
        $this->metadataNormalizerMock = $this->createMock(MetadataNormalizer::class);
        $this->eventMock = $this->createMock(ClassPropertiesChangedEvent::class);
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    ResultIndexer::class => $this->resultIndexerMock,
                    MetadataNormalizer::class => $this->metadataNormalizerMock
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
            ->expects($this->once())
            ->method('getProperties')
            ->willReturn([
                ['class' => $classMock]
            ]);

        $this->resultIndexerMock
            ->expects($this->once())
            ->method('setNormalizer');

        $this->subject->listen(
            $this->eventMock
        );
    }
}
