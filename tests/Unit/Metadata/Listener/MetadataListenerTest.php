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

use oat\generis\model\data\event\ClassPropertyDeletedEvent;
use oat\generis\test\TestCase;
use oat\taoAdvancedSearch\model\Index\Service\ResultIndexer;
use oat\taoAdvancedSearch\model\Metadata\Listener\MetadataListener;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use oat\taoAdvancedSearch\model\Metadata\Normalizer\MetadataNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

class MetadataListenerTest extends TestCase
{
    /** @var MetadataListener */
    private $subject;

    /** @var ClassPropertyDeletedEvent|MockObject */
    private $event;

    /** @var ResultIndexer|MockObject */
    private $resultIndexerMock;

    /** @var MetadataNormalizer|MockObject */
    private $metadataNormalizerMock;

    public function setUp(): void
    {
        $this->event = $this->createMock(ClassPropertyDeletedEvent::class);
        $this->resultIndexerMock = $this->createMock(ResultIndexer::class);
        $this->metadataNormalizerMock = $this->createMock(MetadataNormalizer::class);

        $this->subject = new MetadataListener();

        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    ResultIndexer::class => $this->resultIndexerMock,
                    MetadataNormalizer::class => $this->metadataNormalizerMock
                ]
            )
        );
    }

    public function testListenException(): void
    {
        $this->expectException(UnsupportedEventException::class);
        $this->subject->listen(new stdClass());
    }

    public function testListen(): void
    {
        $this->resultIndexerMock
            ->expects($this->once())
            ->method('setNormalizer');

        $this->resultIndexerMock
            ->expects($this->once())
            ->method('addIndex');

        $this->event
            ->expects($this->once())
            ->method('getClass');

        $this->subject->listen($this->event);
    }
}
