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

namespace oat\taoAdvancedSearch\tests\Unit\Index\Service;

use oat\generis\test\TestCase;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\search\tasks\AddSearchIndexFromArray;
use oat\tao\model\task\migration\ResultUnit;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoAdvancedSearch\model\Index\IndexResource;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;
use oat\taoAdvancedSearch\model\Index\Service\ResultIndexer;
use PHPUnit\Framework\MockObject\MockObject;

class ResultIndexerTest extends TestCase
{
    /** @var NormalizerInterface|MockObject */
    private $normalizer;

    /** @var ResultIndexer */
    private $indexer;

    /** @var QueueDispatcherInterface|MockObject */
    private $queueDispatcher;

    /** @var AdvancedSearchChecker|MockObject  */
    private $advancedSearchChecker;

    public function setUp(): void
    {
        $this->normalizer = $this->createMock(NormalizerInterface::class);
        $this->queueDispatcher = $this->createMock(QueueDispatcherInterface::class);
        $this->advancedSearchChecker = $this->createMock(AdvancedSearchChecker::class);

        $this->indexer = new ResultIndexer();
        $this->indexer->setNormalizer($this->normalizer);
        $this->indexer->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    QueueDispatcherInterface::SERVICE_ID => $this->queueDispatcher,
                    AdvancedSearchChecker::class => $this->advancedSearchChecker,
                ]
            )
        );
    }

    public function testAddIndex(): void
    {
        $resource = new ResultUnit('something');

        $this->advancedSearchChecker->method('isEnabled')->willReturn(true);
        $this->normalizer
            ->expects($this->once())
            ->method('normalize')
            ->with($resource)
            ->willReturn(
                new IndexResource(
                    'id',
                    'label',
                    ['data']
                )
            );

        $this->queueDispatcher
            ->expects($this->once())
            ->method('createTask')
            ->with(
                new AddSearchIndexFromArray(),
                [
                    'id',
                    ['data']
                ],
                __('Adding/Updating search index for label')
            );

        $this->indexer->addIndex($resource);
    }

    public function testAddIndexNonQueued(): void
    {
        $resource = new ResultUnit('something');

        $this->advancedSearchChecker->method('isEnabled')->willReturn(false);
        $this->queueDispatcher
            ->expects($this->never())
            ->method('createTask');

        $this->indexer->addIndex($resource);
    }
}
