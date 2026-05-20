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
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\SearchEngine\Service;

use oat\tao\model\featureFlag\FeatureFlagCheckerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;

/**
 * When {@see FEATURE_FLAG_DISABLE_NESTED_ATTRIBUTES} is enabled, indexing and search use legacy flat metadata fields only.
 * Index mappings may still define nested {@code attributes}; only runtime read/write behaviour changes.
 */
class NestedAttributesFeature
{
    public const FEATURE_FLAG_DISABLE_NESTED_ATTRIBUTES = 'FEATURE_FLAG_ADVANCED_SEARCH_DISABLE_NESTED_ATTRIBUTES';

    private FeatureFlagCheckerInterface $featureFlagChecker;
    private NestedAttributesIndexResolver $nestedAttributesIndexResolver;

    public function __construct(
        FeatureFlagCheckerInterface $featureFlagChecker,
        NestedAttributesIndexResolver $nestedAttributesIndexResolver
    ) {
        $this->featureFlagChecker = $featureFlagChecker;
        $this->nestedAttributesIndexResolver = $nestedAttributesIndexResolver;
    }

    public function isNestedAttributesDisabled(): bool
    {
        return $this->featureFlagChecker->isEnabled(self::FEATURE_FLAG_DISABLE_NESTED_ATTRIBUTES);
    }

    /**
     * @see IndexerInterface::INDEXES_USING_NESTED_ATTRIBUTES
     */
    public function shouldUseNestedAttributes(string $indexName): bool
    {
        return !$this->isNestedAttributesDisabled()
            && $this->nestedAttributesIndexResolver->usesNestedAttributesMapping($indexName);
    }
}
