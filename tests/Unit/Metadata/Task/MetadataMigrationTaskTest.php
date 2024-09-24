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

namespace oat\taoAdvancedSearch\tests\Unit\Metadata\Task;

use PHPUnit\Framework\TestCase;
use oat\tao\test\unit\helpers\NoPrivacyTrait;
use oat\taoAdvancedSearch\model\Index\Service\AbstractIndexMigrationTask;
use oat\taoAdvancedSearch\model\Index\Service\SyncResultIndexer;
use oat\taoAdvancedSearch\model\Metadata\Factory\MetadataResultFilterFactory;
use oat\taoAdvancedSearch\model\Metadata\Normalizer\MetadataNormalizer;
use oat\taoAdvancedSearch\model\Metadata\Service\MetadataResultSearcher;
use oat\taoAdvancedSearch\model\Metadata\Task\MetadataMigrationTask;

class MetadataMigrationTaskTest extends TestCase
{
    use NoPrivacyTrait;

    /** @var MetadataMigrationTask */
    private $subject;

    public function setUp(): void
    {
        $this->subject = new MetadataMigrationTask();
    }

    public function testGetConfig(): void
    {
        $this->assertSame(
            [
                AbstractIndexMigrationTask::OPTION_NORMALIZER => MetadataNormalizer::class,
                AbstractIndexMigrationTask::OPTION_RESULT_SEARCHER => MetadataResultSearcher::class,
                AbstractIndexMigrationTask::OPTION_RESULT_FILTER_FACTORY => MetadataResultFilterFactory::class,
                AbstractIndexMigrationTask::OPTION_INDEXER => SyncResultIndexer::class,
            ],
            $this->invokePrivateMethod($this->subject, 'getConfig', [])
        );
    }
}
