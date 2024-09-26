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

namespace oat\taoAdvancedSearch\tests\Unit\model\Resource\Repository;

use PHPUnit\Framework\TestCase;
use oat\generis\test\ServiceManagerMockTrait;
use oat\oatbox\cache\SimpleCache;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassCachedRepository;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepository;
use PHPUnit\Framework\MockObject\MockObject;

class IndexableClassCacheRepositoryTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var IndexableClassCachedRepository */
    private $subject;

    /** @var IndexableClassRepository|MockObject */
    private $indexableClassRepository;

    /** @var SimpleCache|MockObject */
    private $simpleCache;

    public function setUp(): void
    {
        $this->indexableClassRepository = $this->createMock(IndexableClassRepository::class);
        $this->simpleCache = $this->createMock(SimpleCache::class);
        $this->subject = new IndexableClassCachedRepository();
        $this->subject->setServiceLocator(
            $this->getServiceManagerMock(
                [
                    IndexableClassRepository::class => $this->indexableClassRepository,
                    SimpleCache::SERVICE_ID => $this->simpleCache,
                ]
            )
        );
    }

    public function testFindAll(): void
    {
        $this->indexableClassRepository
            ->method('findAll')
            ->willReturn([]);

        $this->assertSame([], $this->subject->findAll());
    }

    public function testFindAllUris(): void
    {
        $this->indexableClassRepository
            ->method('findAllUris')
            ->willReturn([]);

        $this->assertSame([], $this->subject->findAllUris());
    }

    public function testFindAllUrisFromCache(): void
    {
        $this->simpleCache
            ->method('has')
            ->willReturn(true);

        $this->simpleCache
            ->method('get')
            ->willReturn([]);

        $this->assertSame([], $this->subject->findAllUris());
    }
}
