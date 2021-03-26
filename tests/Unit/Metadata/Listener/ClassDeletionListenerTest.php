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

use Exception;
use oat\generis\model\data\event\ClassDeletedEvent;
use oat\generis\test\MockObject;
use oat\generis\test\TestCase;
use oat\tao\elasticsearch\ElasticSearch;
use oat\tao\model\event\ClassPropertiesChangedEvent;
use oat\taoAdvancedSearch\model\Metadata\Listener\ClassDeletionListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use oat\taoAdvancedSearch\model\Metadata\Normalizer\MetadataNormalizer;
use Psr\Log\LoggerInterface;

class ClassDeletionListenerTest extends TestCase
{
    /** @var MetadataNormalizer */
    private $subject;

    /** @var ClassDeletedEvent|MockObject */
    private $eventMock;

    /** @var LoggerInterface|MockObject */
    private $loggerMock;

    /** @var ElasticSearch|MockObject */
    private $elasticSearchMock;

    public function setUp(): void
    {
        $this->subject = new ClassDeletionListener();

        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->subject->setLogger($this->loggerMock);
        $this->elasticSearchMock = $this->createMock(ElasticSearch::class);

        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    ElasticSearch::SERVICE_ID => $this->elasticSearchMock
                ]
            )
        );

        $this->eventMock = $this->createMock(ClassDeletedEvent::class);
    }

    public function testListenThrowExceptionOnWrongEvent(): void
    {
        $this->expectException(UnsupportedEventException::class);
        $this->subject->listen(
            new ClassPropertiesChangedEvent([])
        );
    }

    public function testListenCatchError(): void
    {
        $this->elasticSearchMock
            ->expects($this->once())
            ->method('remove')
            ->willThrowException(new Exception('Message'));

        $this->loggerMock
            ->expects($this->once())
            ->method('error');

        $this->subject->listen($this->eventMock);
    }

    public function testListenRemovedSuccessfully(): void
    {
        $this->elasticSearchMock
            ->expects($this->once())
            ->method('remove')
            ->willReturn(true);

        $this->loggerMock
            ->expects($this->never())
            ->method('error');

        $this->subject->listen($this->eventMock);
    }
}
