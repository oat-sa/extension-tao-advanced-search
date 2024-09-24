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

use oat\generis\test\ServiceManagerMockTrait;
use PHPUnit\Framework\TestCase;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\SearchInterface;
use oat\tao\model\search\SearchProxy;
use oat\tao\model\task\migration\ResultUnit;
use oat\taoAdvancedSearch\model\Index\IndexResource;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;
use oat\taoAdvancedSearch\model\Index\Service\AdvancedSearchIndexDocumentBuilder;
use oat\taoAdvancedSearch\model\Index\Service\SyncResultIndexer;
use PHPUnit\Framework\MockObject\MockObject;

class SyncResultIndexerTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var NormalizerInterface|MockObject */
    private $normalizer;

    /** @var SyncResultIndexer */
    private $indexer;

    /** @var SearchInterface|MockObject */
    private $search;

    /** @var SearchInterface|MockObject */
    private $indexDocumentBuilder;

    public function setUp(): void
    {
        $this->normalizer = $this->createMock(NormalizerInterface::class);
        $this->search = $this->createMock(SearchInterface::class);
        $this->indexDocumentBuilder = $this->createMock(IndexDocumentBuilderInterface::class);

        $this->indexer = new SyncResultIndexer();
        $this->indexer->setNormalizer($this->normalizer);
        $this->indexer->setServiceLocator(
            $this->getServiceManagerMock(
                [
                    SearchProxy::SERVICE_ID => $this->search,
                    AdvancedSearchIndexDocumentBuilder::class => $this->indexDocumentBuilder,
                ]
            )
        );
    }

    public function testAddIndex(): void
    {
        $resource = new ResultUnit('something');
        $document = $this->createMock(IndexDocument::class);

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

        $this->indexDocumentBuilder
            ->expects($this->once())
            ->method('createDocumentFromArray')
            ->with(
                [
                    'id' => 'id',
                    'body' => ['data'],
                ]
            )
            ->willReturn($document);

        $this->search
            ->expects($this->once())
            ->method('index')
            ->with([$document]);

        $this->indexer->addIndex($resource);
    }
}
