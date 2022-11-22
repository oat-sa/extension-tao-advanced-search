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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\Index\Listener;

use Exception;
use oat\oatbox\event\Event;
use oat\taoAdvancedSearch\model\Index\Handler\EventHandlerInterface;
use oat\taoAdvancedSearch\model\Index\Listener\AgnosticEventListener;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class AgnosticEventListenerTest extends TestCase
{
    private const EVENT1_NAME = 'Event1';
    private const EVENT2_NAME = 'Event2';

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var InvocationMocker */
    private $event1;

    /** @var InvocationMocker */
    private $event2;

    /** @var string */
    private $event1Class;

    /** @var string */
    private $event2Class;

    /** @var EventHandlerInterface|MockObject */
    private $event1Handler;

    /** @var EventHandlerInterface|MockObject */
    private $event1Handler2;

    /** @var EventHandlerInterface|MockObject */
    private $event2Handler;

    public function setUp(): void
    {
        $this->indexer = $this->createMock(IndexerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // getMockClass instead of createMock prevents PHPUnit from
        // reusing the same class
        //
        $event1Class = $this->getMockClass(Event::class, ['getName']);
        $this->event1 = new $event1Class();
        $this->event1
            ->method('getName')
            ->willReturn(self::EVENT1_NAME);

        $this->event2 = $this->createMock(Event::class);
        $this->event2
            ->method('getName')
            ->willReturn(self::EVENT2_NAME);

        $this->event1Class = get_class($this->event1);
        $this->event2Class = get_class($this->event2);

        $this->assertTrue($event1Class !== $this->event2Class);

        $this->event1Handler = $this->createMock(EventHandlerInterface::class);
        $this->event2Handler = $this->createMock(EventHandlerInterface::class);
        $this->event1Handler2 = $this->createMock(EventHandlerInterface::class);
    }

    public function testHandlersAreCalled(): void
    {
        $this->event1Handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->event1);

        $listener = new AgnosticEventListener(
            $this->logger,
            [
                $this->event1Class => [$this->event1Handler],
            ]
        );

        $listener->listen($this->event1);
    }

    public function testNonConfiguredEventThrowsException(): void
    {
        $this->event1Handler
            ->expects($this->never())
            ->method('handle');

        $listener = new AgnosticEventListener(
            $this->logger,
            [
                $this->event1Class => [$this->event1Handler],
            ]
        );

        $this->expectException(UnsupportedEventException::class);

        $listener->listen($this->event2);
    }

    public function testCorrectHandlersAreCalled(): void
    {
        $this->event1Handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->event1);

        $this->event2Handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->event2);

        $listener = new AgnosticEventListener(
            $this->logger,
            [
                $this->event1Class => [$this->event1Handler],
                $this->event2Class => [$this->event2Handler],
            ]
        );

        $listener->listen($this->event1);
        $listener->listen($this->event2);
    }

    public function testExceptionsFromHandlersAreCaptured(): void
    {
        $this->event1Handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->event1)
            ->willThrowException(new \Exception());

        $listener = new AgnosticEventListener(
            $this->logger,
            [
                $this->event1Class => [$this->event1Handler],
            ]
        );

        $listener->listen($this->event1);
    }

    public function testExceptionFromHandlerDoesNotSkipOtherHandlers(): void
    {
        $this->event1Handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->event1)
            ->willThrowException(new Exception());

        $this->event1Handler2
            ->expects($this->once())
            ->method('handle')
            ->with($this->event1);

        $listener = new AgnosticEventListener(
            $this->logger,
            [
                $this->event1Class => [
                    $this->event1Handler,
                    $this->event1Handler2
                ],
            ]
        );

        $listener->listen($this->event1);
    }
}
