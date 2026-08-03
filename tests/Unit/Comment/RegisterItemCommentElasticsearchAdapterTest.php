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

namespace oat\taoAdvancedSearch\tests\Unit\Comment;

use oat\generis\test\ServiceManagerMockTrait;
use oat\oatbox\service\ServiceManager;
use oat\taoAdvancedSearch\model\Comment\ElasticsearchItemCommentAdapter;
use oat\taoAdvancedSearch\scripts\install\RegisterItemCommentElasticsearchAdapter;
use oat\taoItems\model\Comment\ItemCommentPersistenceProxy;
use oat\taoItems\model\Comment\RdfItemCommentAdapter;
use PHPUnit\Framework\TestCase;

class RegisterItemCommentElasticsearchAdapterTest extends TestCase
{
    use ServiceManagerMockTrait;

    public function testReplacesActiveAdapterOnProxy(): void
    {
        $proxy = new ItemCommentPersistenceProxy([
            ItemCommentPersistenceProxy::OPTION_ACTIVE_ADAPTER => RdfItemCommentAdapter::SERVICE_ID,
        ]);

        $services = [];
        $serviceManager = $this->createMock(ServiceManager::class);
        $serviceManager->method('propagate')->willReturnArgument(0);
        $serviceManager
            ->method('has')
            ->willReturnCallback(static function (string $id) use (&$services): bool {
                return isset($services[$id]);
            });
        $serviceManager
            ->method('get')
            ->willReturnCallback(static function (string $id) use (&$services) {
                return $services[$id];
            });
        $serviceManager
            ->method('register')
            ->willReturnCallback(static function (string $id, $service) use (&$services): void {
                $services[$id] = $service;
            });

        $services[ItemCommentPersistenceProxy::SERVICE_ID] = $proxy;

        $script = new RegisterItemCommentElasticsearchAdapter();
        $script->setServiceLocator($serviceManager);
        $script([]);

        $this->assertArrayHasKey(ElasticsearchItemCommentAdapter::SERVICE_ID, $services);
        $this->assertSame(
            ElasticsearchItemCommentAdapter::SERVICE_ID,
            $services[ItemCommentPersistenceProxy::SERVICE_ID]->getOption(
                ItemCommentPersistenceProxy::OPTION_ACTIVE_ADAPTER
            )
        );
    }
}
