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

namespace oat\taoAdvancedSearch\scripts\install;

use oat\oatbox\extension\InstallAction;
use oat\oatbox\reporting\Report;
use oat\taoAdvancedSearch\model\Comment\ElasticsearchItemCommentAdapter;
use oat\taoAdvancedSearch\model\Comment\ItemCommentIndexManager;
use oat\taoItems\model\Comment\ItemCommentPersistenceProxy;

/**
 * Creates item-comments ES index and replaces taoItems RDF comment persistence with Elasticsearch.
 */
class RegisterItemCommentElasticsearchAdapter extends InstallAction
{
    public function __invoke($params = [])
    {
        $serviceManager = $this->getServiceManager();

        $indexManager = new ItemCommentIndexManager();
        $serviceManager->propagate($indexManager);
        $serviceManager->register(ItemCommentIndexManager::SERVICE_ID, $indexManager);

        $adapter = new ElasticsearchItemCommentAdapter();
        $serviceManager->propagate($adapter);
        $serviceManager->register(ElasticsearchItemCommentAdapter::SERVICE_ID, $adapter);

        $indexName = $indexManager->ensureIndexExists();

        if ($serviceManager->has(ItemCommentPersistenceProxy::SERVICE_ID)) {
            /** @var ItemCommentPersistenceProxy $proxy */
            $proxy = $serviceManager->get(ItemCommentPersistenceProxy::SERVICE_ID);
            $proxy->setOption(
                ItemCommentPersistenceProxy::OPTION_ACTIVE_ADAPTER,
                ElasticsearchItemCommentAdapter::SERVICE_ID
            );
            $serviceManager->register(ItemCommentPersistenceProxy::SERVICE_ID, $proxy);
        }

        return Report::createSuccess(
            sprintf('Item comments persistence uses Elasticsearch index "%s"', $indexName)
        );
    }
}
