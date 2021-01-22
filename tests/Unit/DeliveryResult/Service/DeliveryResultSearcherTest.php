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

namespace oat\taoAdvancedSearch\tests\Unit\DeliveryResult\Service;

use oat\generis\model\kernel\persistence\smoothsql\search\ResourceSearchService;
use oat\generis\test\TestCase;
use oat\tao\model\task\migration\service\ResultFilter;
use oat\tao\test\unit\helpers\NoPrivacyTrait;
use oat\taoAdvancedSearch\model\DeliveryResult\Service\DeliveryResultSearcher;
use oat\taoDelivery\model\execution\DeliveryExecutionInterface;
use oat\taoDelivery\model\execution\DeliveryExecutionService;
use oat\taoOutcomeUi\model\Builder\ResultsServiceBuilder;
use oat\taoOutcomeUi\model\ResultsService;
use oat\taoResultServer\models\classes\ResultManagement;
use PHPUnit\Framework\MockObject\MockObject;

class DeliveryResultSearcherTest extends TestCase
{
    use NoPrivacyTrait;

    /** @var DeliveryResultSearcher */
    private $subject;

    /** @var ResultsService|MockObject */
    private $resultsService;

    /** @var ResultsServiceBuilder|MockObject */
    private $resultsServiceBuilder;

    /** @var ResourceSearchService|MockObject */
    private $deliveryExecutionService;

    /** @var ResultManagement|MockObject */
    private $resultManagement;

    public function setUp(): void
    {
        $this->resultsService = $this->createMock(ResultsService::class);
        $this->resultManagement = $this->createMock(ResultManagement::class);
        $this->resultsServiceBuilder = $this->createMock(ResultsServiceBuilder::class);
        $this->deliveryExecutionService = $this->createMock(DeliveryExecutionService::class);

        $this->resultsServiceBuilder
            ->method('build')
            ->willReturn($this->resultsService);

        $this->resultsService
            ->method('getImplementation')
            ->willReturn($this->resultManagement);

        $this->subject = new DeliveryResultSearcher();
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    ResultsServiceBuilder::class => $this->resultsServiceBuilder,
                    DeliveryExecutionService::SERVICE_ID => $this->deliveryExecutionService,
                ]
            )
        );
    }

    public function testSearch(): void
    {
        $deliveryExecution = $this->createMock(DeliveryExecutionInterface::class);

        $this->deliveryExecutionService
            ->method('getDeliveryExecution')
            ->willReturn($deliveryExecution);

        $this->resultManagement
            ->method('getResultByDelivery')
            ->with(
                [],
                [
                    'offset' => 0,
                    'limit' => 5,
                    'recursive' => true,
                ]
            )
            ->willReturn(
                [
                    [
                        'deliveryResultIdentifier' => 1,
                    ]
                ]
            );

        $collection = $this->subject->search(
            new ResultFilter(
                [
                    'start' => 0,
                    'end' => 5,
                    'max' => 10,
                ]
            )
        );

        $this->assertEquals(1, $collection->count());
        $this->assertEquals($deliveryExecution, $collection->offsetGet(0)->getResult());
    }
}
