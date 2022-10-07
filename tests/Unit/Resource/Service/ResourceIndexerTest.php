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
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\Resource\Service;

use oat\tao\model\search\tasks\UpdateResourceInIndex;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexer;
use oat\taoAdvancedSearch\model\Resource\Service\SyncResourceResultIndexer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ResourceIndexerTest extends TestCase
{
    /** @var SyncResourceResultIndexer */
    private $subject;

    /** @var QueueDispatcherInterface|MockObject */
    private $queueDispatcher;

    public function setUp(): void
    {
        $this->queueDispatcher = $this->createMock(QueueDispatcherInterface::class);

        $this->subject = new ResourceIndexer($this->queueDispatcher);
    }

    public function testAddIndex(): void
    {
        $uris = [
            'uri1',
            'uri2',
        ];

        $this->queueDispatcher
            ->expects($this->once())
            ->method('createTask')
            ->with(
                new UpdateResourceInIndex(),
                [
                    [
                        'uri1',
                        'uri2',
                    ]
                ],
                'Indexing resource(s) uri1,uri2...'
            );

        $this->subject->addIndex($uris);
    }
}
