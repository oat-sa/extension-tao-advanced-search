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

use InvalidArgumentException;
use oat\generis\test\TestCase;
use oat\taoAdvancedSearch\model\DeliveryResult\Normalizer\DeliveryResultNormalizer;
use oat\taoDelivery\model\execution\DeliveryExecutionInterface;
use stdClass;

class DeliveryResultNormalizerTest extends TestCase
{
    /** @var DeliveryResultNormalizer */
    private $subject;

    public function setUp(): void
    {
        $this->subject = new DeliveryResultNormalizer();
    }

    public function testNormalizeOnlyAcceptsDeliveryExecution(): void
    {
        $this->expectExceptionMessage('$deliveryExecution must be instance of ' . DeliveryExecutionInterface::class);
        $this->expectException(InvalidArgumentException::class);

        $this->subject->normalize(new stdClass());
    }
}
