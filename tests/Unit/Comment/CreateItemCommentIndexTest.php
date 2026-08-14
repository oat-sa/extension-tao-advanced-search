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
 * Foundation, Inc., 31 Milk St # 960789 Boston, MA 02196 USA
 *
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\Comment;

use oat\oatbox\reporting\Report;
use oat\oatbox\service\ServiceManager;
use oat\taoAdvancedSearch\model\Comment\ItemCommentIndexManager;
use oat\taoAdvancedSearch\scripts\install\CreateItemCommentIndex;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

class CreateItemCommentIndexTest extends TestCase
{
    public function testInvokeEnsuresIndexViaContainer(): void
    {
        /** @var ItemCommentIndexManager|MockObject $indexManager */
        $indexManager = $this->createMock(ItemCommentIndexManager::class);
        $indexManager
            ->expects($this->once())
            ->method('ensureIndexExists')
            ->willReturn('test-item-comments');

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('get')
            ->with(ItemCommentIndexManager::class)
            ->willReturn($indexManager);

        $serviceManager = $this->createMock(ServiceManager::class);
        $serviceManager->method('getContainer')->willReturn($container);

        $script = new CreateItemCommentIndex();
        $script->setServiceLocator($serviceManager);

        $report = $script([]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertSame(Report::TYPE_SUCCESS, $report->getType());
        $this->assertStringContainsString('test-item-comments', $report->getMessage());
    }

    public function testInvokeReturnsErrorReportWhenIndexCreationFails(): void
    {
        /** @var ItemCommentIndexManager|MockObject $indexManager */
        $indexManager = $this->createMock(ItemCommentIndexManager::class);
        $indexManager
            ->expects($this->once())
            ->method('ensureIndexExists')
            ->willThrowException(new RuntimeException('Connection refused'));

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('get')
            ->with(ItemCommentIndexManager::class)
            ->willReturn($indexManager);

        $serviceManager = $this->createMock(ServiceManager::class);
        $serviceManager->method('getContainer')->willReturn($container);

        $script = new CreateItemCommentIndex();
        $script->setServiceLocator($serviceManager);

        $report = $script([]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertSame(Report::TYPE_ERROR, $report->getType());
        $this->assertStringContainsString('not available yet', $report->getMessage());
        $this->assertStringContainsString('Connection refused', $report->getMessage());
    }
}
