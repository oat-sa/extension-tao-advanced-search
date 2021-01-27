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

namespace oat\taoAdvancedSearch\tests\model\Cache;

use core_kernel_classes_Class;
use core_kernel_classes_Property;
use Doctrine\Common\Collections\ArrayCollection;
use oat\generis\model\data\Ontology;
use oat\generis\test\MockObject;
use oat\generis\test\OntologyMockTrait;
use oat\generis\test\TestCase;
use oat\taoAdvancedSearch\model\Cache\PropertyTreeGenerator;

class PropertyTreeGeneratorTest extends TestCase
{
    use OntologyMockTrait;

    /** @var PropertyTreeGenerator */
    private $subject;

    /** @var Ontology|MockObject */
    private $modelMock;

    /** @var core_kernel_classes_Class|MockObject */
    private $classMock;

    /** @var core_kernel_classes_Property|MockObject */
    private $propertyMock;

    public function setUp(): void
    {
        $this->subject = new PropertyTreeGenerator();
        $this->modelMock = $this->createMock(Ontology::class);
        $this->classMock = $this->createMock(core_kernel_classes_Class::class);
        $this->propertyMock = $this->createMock(core_kernel_classes_Property::class);

        $this->subject->setModel($this->modelMock);
    }

    public function testGetClassPropertyTree(): void
    {
        $this->modelMock
            ->expects($this->once())
            ->method('getClass')
            ->willReturn($this->classMock);

        $this->classMock
            ->expects($this->exactly(2))
            ->method('getProperties')
            ->willReturn(
                [
                    $this->propertyMock
                ]
            );

        $this->classMock
            ->expects($this->once())
            ->method('getSubClasses')
            ->willReturn(
                [
                    $this->classMock
                ]
            );

        $this->classMock
            ->expects($this->exactly(2))
            ->method('getUri')
            ->willReturnOnConsecutiveCalls(
                'classUri',
                'parentClassUri'
            );

        $this->propertyMock
            ->expects($this->exactly(2))
            ->method('getUri')
            ->willReturnOnConsecutiveCalls(
                'PropertyUri',
                'PropertyUri1'
            );

        $this->classMock
            ->expects($this->once())
            ->method('getParentClasses')
            ->willReturn(
                [
                    $this->classMock
                ]
            );

        $this->propertyMock
            ->expects($this->exactly(2))
            ->method('getLabel')
            ->willReturnOnConsecutiveCalls(
                'PropertyLabel',
                'PropertyLabel1'
            );

        $result = $this->subject->getClassPropertyTree('someUri');
        $this->assertInstanceOf(ArrayCollection::class, $result);
        $this->assertEquals('someUri', $result->first()->getUri());
        $this->assertEmpty($result->first()->getParentClass());
        $this->assertInstanceOf(ArrayCollection::class, $result->first()->getProperties());

        $this->assertEquals(
            $result->first()->getProperties()->first()['propertyUri'],
            'PropertyUri'
        );

        $this->assertEquals(
            $result->first()->getProperties()->first()['propertyLabel'],
            'PropertyLabel'
        );

        $this->assertEquals(
            $result->get(1)->getParentClass(),
            'parentClassUri'
        );

        $this->assertEquals(
            $result->get(1)->getProperties()->first()['propertyUri'],
            'PropertyUri1'
        );

        $this->assertEquals(
            $result->get(1)->getProperties()->first()['propertyLabel'],
            'PropertyLabel1'
        );
    }
}
