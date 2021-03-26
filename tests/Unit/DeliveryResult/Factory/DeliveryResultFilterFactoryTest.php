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

namespace oat\taoAdvancedSearch\tests\Unit\DeliveryResult\Factory;

use core_kernel_classes_Resource;
use oat\generis\model\kernel\persistence\smoothsql\search\ResourceSearchService;
use oat\generis\test\TestCase;
use oat\search\ResultSet;
use oat\tao\test\unit\helpers\NoPrivacyTrait;
use oat\taoAdvancedSearch\model\DeliveryResult\Factory\DeliveryResultFilterFactory;
use oat\taoOutcomeUi\model\Builder\ResultsServiceBuilder;
use oat\taoOutcomeUi\model\ResultsService;
use oat\taoResultServer\models\classes\ResultManagement;
use PHPUnit\Framework\MockObject\MockObject;

class DeliveryResultFilterFactoryTest extends TestCase
{
    use NoPrivacyTrait;

    /** @var DeliveryResultFilterFactory */
    private $subject;

    /** @var ResultsService|MockObject */
    private $resultsService;

    /** @var ResultsServiceBuilder|MockObject */
    private $resultsServiceBuilder;

    /** @var ResourceSearchService|MockObject */
    private $resourceSearchService;

    /** @var ResultManagement|MockObject */
    private $resultManagement;

    public function setUp(): void
    {
        $this->resultsService = $this->createMock(ResultsService::class);
        $this->resultManagement = $this->createMock(ResultManagement::class);
        $this->resultsServiceBuilder = $this->createMock(ResultsServiceBuilder::class);
        $this->resourceSearchService = $this->createMock(ResourceSearchService::class);

        $this->resultsServiceBuilder
            ->method('build')
            ->willReturn($this->resultsService);

        $this->resultsService
            ->method('getImplementation')
            ->willReturn($this->resultManagement);

        $this->subject = new DeliveryResultFilterFactory();
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    ResultsServiceBuilder::class => $this->resultsServiceBuilder,
                    ResourceSearchService::class => $this->resourceSearchService,
                ]
            )
        );
    }

    public function testGetMax(): void
    {
        $this->resourceSearchService
            ->method('findByClassUri')
            ->willReturn(
                new ResultSet(
                    [
                        new core_kernel_classes_Resource('uri')
                    ],
                    1
                )
            );

        $this->resultManagement
            ->method('countResultByDelivery')
            ->with(['uri'])
            ->willReturn(777);

        $this->assertEquals(777, $this->invokePrivateMethod($this->subject, 'getMax', []));
    }
}
