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

namespace oat\taoAdvancedSearch\tests\Unit\model\Metadata\Repository;

use oat\generis\model\data\Ontology;
use oat\generis\test\TestCase;
use oat\oatbox\cache\SimpleCache;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriCachedRepository;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriRepository;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;

class ClassUriCachedRepositoryTest extends TestCase
{
    /** @var ClassUriCachedRepository */
    private $subject;

    /** @var ClassUriRepositoryInterface */
    private $classUriRepository;

    /** @var Ontology|MockObject */
    private $ontology;

    /** @var SimpleCache|MockObject */
    private $simpleCache;

    public function setUp(): void
    {
        $this->ontology = $this->createMock(Ontology::class);
        $this->classUriRepository = $this->createMock(ClassUriRepositoryInterface::class);
        $this->simpleCache = $this->createMock(SimpleCache::class);

        $this->subject = $this->createMock(ClassUriRepository::class);
        $this->subject = new ClassUriCachedRepository();
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    Ontology::SERVICE_ID => $this->ontology,
                    SimpleCache::SERVICE_ID => $this->classUriRepository,
                    ClassUriRepository::class => $this->simpleCache,
                ]
            )
        );
    }

    public function testFindAllFromCache(): void
    {
        $classes = [];

        $this->simpleCache
            ->method('has')
            ->willReturn(true);

        $this->simpleCache
            ->method('get')
            ->willReturn($classes);

        $this->assertEquals($classes, $this->subject->findAll());
    }

    public function testFindAllFromRepository(): void
    {
        $classes = [];

        $this->classUriRepository
            ->method('findAll')
            ->willReturn($classes);

        $this->simpleCache
            ->method('has')
            ->willReturn(false);

        $this->assertEquals($classes, $this->subject->findAll());
    }

    public function testGetTotalFromCache(): void
    {
        $this->simpleCache
            ->method('has')
            ->willReturn(true);

        $this->simpleCache
            ->method('get')
            ->willReturn(1);

        $this->assertEquals(1, $this->subject->getTotal());
    }
}
