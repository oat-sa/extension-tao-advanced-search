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

use oat\generis\test\ServiceManagerMockTrait;
use PHPUnit\Framework\TestCase;
use oat\tao\test\unit\helpers\NoPrivacyTrait;
use oat\taoAdvancedSearch\model\DeliveryResult\Factory\DeliveryResultFilterFactory;
use oat\taoAdvancedSearch\model\DeliveryResult\Repository\DeliveryResultRepository;
use oat\taoAdvancedSearch\model\DeliveryResult\Repository\DeliveryResultRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;

class DeliveryResultFilterFactoryTest extends TestCase
{
    use NoPrivacyTrait;
    use ServiceManagerMockTrait;

    /** @var DeliveryResultFilterFactory */
    private $subject;

    /** @var DeliveryResultRepositoryInterface|MockObject */
    private $deliveryResultRepository;

    public function setUp(): void
    {
        $this->deliveryResultRepository = $this->createMock(DeliveryResultRepositoryInterface::class);

        $this->subject = new DeliveryResultFilterFactory();
        $this->subject->setServiceLocator(
            $this->getServiceManagerMock(
                [
                    DeliveryResultRepository::class => $this->deliveryResultRepository,
                ]
            )
        );
    }

    public function testGetMax(): void
    {
        $this->deliveryResultRepository
            ->method('getTotal')
            ->willReturn(777);

        $this->assertEquals(777, $this->invokePrivateMethod($this->subject, 'getMax', []));
    }
}
