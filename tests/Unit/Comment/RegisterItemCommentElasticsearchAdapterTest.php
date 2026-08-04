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

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Response\Elasticsearch;
use oat\oatbox\service\ServiceManager;
use oat\taoAdvancedSearch\model\Comment\ElasticsearchItemCommentAdapter;
use oat\taoAdvancedSearch\model\Comment\ItemCommentIndexManager;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use oat\taoAdvancedSearch\scripts\install\RegisterItemCommentElasticsearchAdapter;
use oat\taoItems\model\Comment\ItemCommentPersistenceProxy;
use oat\taoItems\model\Comment\RdfItemCommentAdapter;
use DG\BypassFinals;
use PHPUnit\Framework\TestCase;

class RegisterItemCommentElasticsearchAdapterTest extends TestCase
{
    public function testReplacesActiveAdapterOnProxyAndEnsuresIndex(): void
    {
        BypassFinals::enable();

        $proxy = new ItemCommentPersistenceProxy([
            ItemCommentPersistenceProxy::OPTION_ACTIVE_ADAPTER => RdfItemCommentAdapter::SERVICE_ID,
        ]);

        $existsResponse = $this->createMock(Elasticsearch::class);
        $existsResponse->method('asBool')->willReturn(true);

        $indices = $this->createMock(Indices::class);
        $indices->expects($this->once())->method('exists')->willReturn($existsResponse);

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $prefixer = $this->createMock(IndexPrefixer::class);
        $prefixer->method('prefix')->willReturn('test-item-comments');

        $services = [
            Client::class => $client,
            IndexPrefixer::class => $prefixer,
            ItemCommentPersistenceProxy::SERVICE_ID => $proxy,
        ];

        $serviceManager = $this->createMock(ServiceManager::class);
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
            ->willReturnCallback(static function (string $id, $service) use (&$services, $serviceManager): void {
                if (method_exists($service, 'setServiceLocator')) {
                    $service->setServiceLocator($serviceManager);
                }
                $services[$id] = $service;
            });
        $serviceManager
            ->method('propagate')
            ->willReturnCallback(static function ($service) use ($serviceManager) {
                if (method_exists($service, 'setServiceLocator')) {
                    $service->setServiceLocator($serviceManager);
                }
                return $service;
            });

        $script = new RegisterItemCommentElasticsearchAdapter();
        $script->setServiceLocator($serviceManager);
        $report = $script([]);

        $this->assertNotNull($report);
        $this->assertArrayHasKey(ItemCommentIndexManager::SERVICE_ID, $services);
        $this->assertArrayHasKey(ElasticsearchItemCommentAdapter::SERVICE_ID, $services);
        $this->assertSame(
            ElasticsearchItemCommentAdapter::SERVICE_ID,
            $services[ItemCommentPersistenceProxy::SERVICE_ID]->getOption(
                ItemCommentPersistenceProxy::OPTION_ACTIVE_ADAPTER
            )
        );
    }
}
