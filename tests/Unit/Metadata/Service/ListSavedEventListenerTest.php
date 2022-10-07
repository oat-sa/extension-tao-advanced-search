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

namespace oat\taoAdvancedSearch\tests\Unit\model\Metadata\Service;

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\ForwardCompatibility\Result;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use oat\generis\test\ServiceManagerMockTrait;
use oat\tao\model\Lists\Business\Event\ListSavedEvent;
use oat\taoAdvancedSearch\model\Metadata\Service\ListSavedEventListener;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ListSavedEventListenerTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var ListSavedEventListener */
    private $subject;

    /** @var ResourceIndexer|MockObject */
    private $resourceIndexer;

    /** @var QueryBuilder|MockObject */
    private $queryBuilder;

    /** @var ExpressionBuilder|MockObject */
    private $expressionBuilder;

    public function setUp(): void
    {
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->resourceIndexer = $this->createMock(ResourceIndexer::class);
        $this->expressionBuilder = $this->createMock(ExpressionBuilder::class);

        $this->subject = new ListSavedEventListener($this->resourceIndexer, $this->queryBuilder);
    }

    public function testListenAndProcessInChunks(): void
    {
        $result1 = $this->createMock(Result::class);
        $iterator1 = $this->createMock(ResultStatement::class);
        $result2 = $this->createMock(Result::class);
        $iterator2 = $this->createMock(ResultStatement::class);

        $this->queryBuilder
            ->method('expr')
            ->willReturn($this->expressionBuilder);

        $queryBuilderSelfReturn = [
            'resetQueryParts',
            'select',
            'from',
            'andWhere',
            'andWhere',
            'setParameters',
            'setParameter',
        ];

        foreach ($queryBuilderSelfReturn as $method) {
            $this->queryBuilder
                ->method($method)
                ->willReturnSelf();
        }

        $this->queryBuilder
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(
                function () use ($result1, $result2) {
                    static $count = 0;

                    if ($count++ == 0) {
                        return $result1;
                    }

                    return $result2;
                }
            );

        $result1
            ->expects($this->once())
            ->method('getIterator')
            ->willReturn($iterator1);

        $iterator1
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn(['uri']);

        $result2
            ->expects($this->once())
            ->method('getIterator')
            ->willReturn($iterator2);

        $iterator2
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn(['uri1', 'uri2']);

        $this->resourceIndexer
            ->expects($this->exactly(2))
            ->method('addIndex')
            ->withConsecutive(
                [
                    ['uri1']
                ],
                [
                    ['uri2']
                ]
            );

        $this->subject
            ->setChunkSize(1)
            ->listen(new ListSavedEvent('listUri'));
    }
}
