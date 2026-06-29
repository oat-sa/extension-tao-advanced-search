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
 * Foundation, Inc., 31 Milk St # 960789 Boston, MA 02196 USA
 *
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine\Service;

use oat\tao\model\featureFlag\FeatureFlagCheckerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesFeature;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesIndexResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NestedAttributesFeatureTest extends TestCase
{
    /** @var FeatureFlagCheckerInterface|MockObject */
    private $featureFlagChecker;

    protected function setUp(): void
    {
        $this->featureFlagChecker = $this->createMock(FeatureFlagCheckerInterface::class);
    }

    public function testShouldUseNestedAttributesWhenFlagDisabledOnItemsIndex(): void
    {
        $this->featureFlagChecker
            ->method('isEnabled')
            ->with(NestedAttributesFeature::FEATURE_FLAG_DISABLE_NESTED_ATTRIBUTES)
            ->willReturn(false);

        $sut = new NestedAttributesFeature($this->featureFlagChecker, new NestedAttributesIndexResolver());

        $this->assertTrue($sut->shouldUseNestedAttributes(IndexerInterface::ITEMS_INDEX));
    }

    public function testShouldNotUseNestedAttributesWhenFlagEnabled(): void
    {
        $this->featureFlagChecker
            ->method('isEnabled')
            ->with(NestedAttributesFeature::FEATURE_FLAG_DISABLE_NESTED_ATTRIBUTES)
            ->willReturn(true);

        $sut = new NestedAttributesFeature($this->featureFlagChecker, new NestedAttributesIndexResolver());

        $this->assertFalse($sut->shouldUseNestedAttributes(IndexerInterface::ITEMS_INDEX));
    }

    public function testShouldNotUseNestedAttributesOnUnlistedIndex(): void
    {
        $this->featureFlagChecker
            ->method('isEnabled')
            ->willReturn(false);

        $sut = new NestedAttributesFeature($this->featureFlagChecker, new NestedAttributesIndexResolver());

        $this->assertFalse($sut->shouldUseNestedAttributes(IndexerInterface::DELIVERY_RESULTS_INDEX));
    }
}
