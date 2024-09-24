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

use core_kernel_classes_Class;
use oat\generis\model\data\Ontology;
use PHPUnit\Framework\TestCase;
use oat\generis\test\ServiceManagerMockTrait;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepository;
use PHPUnit\Framework\MockObject\MockObject;

class IndexableClassRepositoryTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var IndexableClassRepository */
    private $subject;

    /** @var Ontology|MockObject */
    private $ontology;

    public function setUp(): void
    {
        $this->ontology = $this->createMock(Ontology::class);
        $this->subject = new IndexableClassRepository();
        $this->subject->withMenuPerspectives([]);
        $this->subject->setServiceLocator(
            $this->getServiceManagerMock(
                [
                    Ontology::SERVICE_ID => $this->ontology,
                ]
            )
        );
    }

    public function testFindAll(): void
    {
        $classMock = $this->createMock(core_kernel_classes_Class::class);

        $this->ontology
            ->method('getClass')
            ->willReturn($classMock);

        $this->assertIsArray($this->subject->findAll());
    }
}
