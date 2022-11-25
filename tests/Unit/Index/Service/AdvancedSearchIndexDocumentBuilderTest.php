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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\Index\Service;

use oat\tao\model\search\index\IndexService;
use oat\taoAdvancedSearch\model\Index\Service\AdvancedSearchIndexDocumentBuilder;
use oat\taoMediaManager\model\relation\service\IdDiscoverService;
use oat\taoQtiItem\model\qti\parser\ElementReferencesExtractor;
use Psr\Container\ContainerInterface;
use taoQtiTest_models_classes_QtiTestService as QtiTestService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdvancedSearchIndexDocumentBuilderTest extends TestCase
{
    /** @var AdvancedSearchIndexDocumentBuilder */
    private $sut;

    /** @var QtiTestService|MockObject */
    private $qtiTestService;

    /** @var IdDiscoverService|MockObject */
    private $idDiscoverService;

    /** @var ElementReferencesExtractor|MockObject */
    private $elementReferencesExtractor;

    /** @var IndexService|MockObject */
    private $indexService;

    /** @var MockObject|ContainerInterface */
    private $container;

    public function setUp(): void
    {
        $this->qtiTestService = $this->createMock(QtiTestService::class);
        $this->elementReferencesExtractor = $this->createMock(ElementReferencesExtractor::class);
        $this->idDiscoverService = $this->createMock(IdDiscoverService::class);
        $this->indexService = $this->createMock(IndexService::class);
        $this->container = $this->createMock(ContainerInterface::class);

        $this->container
            ->expects($this->once())
            ->method('get')
            ->willReturn($this->idDiscoverService);

        $this->sut = new AdvancedSearchIndexDocumentBuilder(
            $this->qtiTestService,
            $this->elementReferencesExtractor,
            $this->indexService,
            $this->container,
        );
    }

    public function testCreateDocumentFromResourceItem(): void
    {
        // @TODO Test this method covering all the scopes
        $this->markTestIncomplete();

        $this->sut->createDocumentFromResource();
    }

    public function testCreateDocumentFromResourceTest(): void
    {
        // @TODO Test this method covering all the scopes
        $this->markTestIncomplete();

        $this->sut->createDocumentFromResource();
    }
}
