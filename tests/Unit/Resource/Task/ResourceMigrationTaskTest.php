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

namespace oat\taoAdvancedSearch\tests\Unit\Resource\Task;

use PHPUnit\Framework\TestCase;
use oat\tao\test\unit\helpers\NoPrivacyTrait;
use oat\taoAdvancedSearch\model\Index\Service\AbstractIndexMigrationTask;
use oat\taoAdvancedSearch\model\Resource\Factory\ResourceResultFilterFactory;
use oat\taoAdvancedSearch\model\Resource\Task\ResourceMigrationTask;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceSearcher;
use oat\taoAdvancedSearch\model\Resource\Service\SyncResourceResultIndexer;

class ResourceMigrationTaskTest extends TestCase
{
    use NoPrivacyTrait;

    /** @var ResourceMigrationTask */
    private $subject;

    public function setUp(): void
    {
        $this->subject = new ResourceMigrationTask();
    }

    public function testGetConfig(): void
    {
        $this->assertSame(
            [
                AbstractIndexMigrationTask::OPTION_RESULT_SEARCHER => ResourceSearcher::class,
                AbstractIndexMigrationTask::OPTION_RESULT_FILTER_FACTORY => ResourceResultFilterFactory::class,
                AbstractIndexMigrationTask::OPTION_INDEXER => SyncResourceResultIndexer::class,
            ],
            $this->invokePrivateMethod($this->subject, 'getConfig', [])
        );
    }
}
