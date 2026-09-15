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

use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexConfigurationProvider;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexDefaultSortFieldResolver;
use PHPUnit\Framework\TestCase;

class IndexDefaultSortFieldResolverTest extends TestCase
{
    public function testResolveForIndexUsesConfigMapAndFallback(): void
    {
        $resolver = new IndexDefaultSortFieldResolver(
            new IndexConfigurationProvider([
                [
                    'index' => IndexerInterface::DELIVERY_RESULTS_INDEX,
                    'defaultSortField' => 'delivery_execution_start_time.raw',
                ],
                [
                    'index' => IndexerInterface::ITEMS_INDEX,
                    'defaultSortField' => 'updated_at.raw',
                ],
            ])
        );

        $this->assertSame(
            'delivery_execution_start_time.raw',
            $resolver->resolveForIndex(IndexerInterface::DELIVERY_RESULTS_INDEX)
        );
        $this->assertSame(
            'delivery_execution_start_time.raw',
            $resolver->resolveForIndex('tenant_delivery-results')
        );
        $this->assertSame('updated_at.raw', $resolver->resolveForIndex(IndexerInterface::ITEMS_INDEX));
        $this->assertSame('updated_at.raw', $resolver->resolveForIndex('unknown-index'));
    }

    public function testSetIndexFileRefreshesDefaultSortField(): void
    {
        $provider = new IndexConfigurationProvider([
            [
                'index' => IndexerInterface::ITEMS_INDEX,
                'defaultSortField' => 'updated_at.raw',
            ],
        ]);
        $resolver = new IndexDefaultSortFieldResolver($provider);

        $this->assertSame('updated_at.raw', $resolver->resolveForIndex(IndexerInterface::ITEMS_INDEX));

        $indexFile = tempnam(sys_get_temp_dir(), 'idx');
        file_put_contents(
            $indexFile,
            '<?php return [[' .
            "'index' => 'items'," .
            "'defaultSortField' => 'custom_sort.raw'," .
            ']];'
        );

        try {
            $provider->setIndexFile($indexFile);
            $this->assertSame(
                'custom_sort.raw',
                $resolver->resolveForIndex(IndexerInterface::ITEMS_INDEX)
            );
        } finally {
            unlink($indexFile);
        }
    }
}
