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

namespace oat\taoAdvancedSearch\tests\Unit\Resource\Service;

use core_kernel_classes_Resource;
use oat\generis\test\TestCase;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\index\IndexService;
use oat\tao\model\search\SearchInterface;
use oat\tao\model\search\SearchProxy;
use oat\tao\model\task\migration\ResultUnit;
use oat\taoAdvancedSearch\model\Index\IndexResource;
use oat\taoAdvancedSearch\model\Index\Service\ResultIndexer;
use oat\taoAdvancedSearch\model\Resource\Service\SyncResourceResultIndexer;
use PHPUnit\Framework\MockObject\MockObject;

class SyncResourceResultIndexerTest extends TestCase
{
    /** @var SyncResourceResultIndexer */
    private $indexer;

    /** @var IndexService|MockObject */
    private $indexerService;

    /** @var SearchInterface|MockObject */
    private $search;

    /** @var SearchInterface|MockObject */
    private $indexDocumentBuilder;

    public function setUp(): void
    {
        $this->indexerService = $this->createMock(IndexService::class);
        $this->search = $this->createMock(SearchInterface::class);
        $this->indexDocumentBuilder = $this->createMock(IndexDocumentBuilderInterface::class);

        $this->indexer = new SyncResourceResultIndexer();
        $this->indexer->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    IndexService::SERVICE_ID => $this->indexerService,
                    SearchProxy::SERVICE_ID => $this->search,
                    IndexDocumentBuilderInterface::class => $this->indexDocumentBuilder,
                ]
            )
        );

        $this->indexerService
            ->method('getDocumentBuilder')
            ->willReturn($this->indexDocumentBuilder);
    }

    public function testAddIndex(): void
    {
        $resource = $this->createMock(core_kernel_classes_Resource::class);
        $document = $this->createMock(IndexDocument::class);

        $this->indexDocumentBuilder
            ->expects($this->once())
            ->method('createDocumentFromResource')
            ->with($resource)
            ->willReturn($document);

        $this->search
            ->expects($this->once())
            ->method('index')
            ->with([$document]);

        $this->indexer->addIndex($resource);
    }
}
