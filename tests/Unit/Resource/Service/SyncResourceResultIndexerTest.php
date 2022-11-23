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
 * Copyright (c) 2021-2022 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\Resource\Service;

use core_kernel_classes_Resource;
use oat\generis\test\TestCase;
use oat\oatbox\log\LoggerService;
use oat\taoAdvancedSearch\model\Index\Listener\ResourceOperationMediator;
use oat\taoAdvancedSearch\model\Resource\Service\SyncResourceResultIndexer;
use PHPUnit\Framework\MockObject\MockObject;

class SyncResourceResultIndexerTest extends TestCase
{
    /** @var SyncResourceResultIndexer */
    private $indexer;

    /** @var ResourceOperationMediator|MockObject */
    private $mediator;

    public function setUp(): void
    {
        $this->mediator = $this->createMock(ResourceOperationMediator::class);

        $this->indexer = new SyncResourceResultIndexer();
        $this->indexer->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    ResourceOperationMediator::class => $this->mediator,
                    LoggerService::SERVICE_ID => $this->createMock(
                        LoggerService::class
                    ),
                ]
            )
        );
    }

    public function testAddIndex(): void
    {
        $resource = $this->createMock(core_kernel_classes_Resource::class);

        $this->mediator
            ->expects($this->once())
            ->method('handleAddIndex')
            ->with($resource);

        $this->indexer->addIndex($resource);
    }
}
