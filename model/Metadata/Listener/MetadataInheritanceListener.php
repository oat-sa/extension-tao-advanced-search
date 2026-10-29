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

namespace oat\taoAdvancedSearch\model\Metadata\Listener;

use common_Logger;
use core_kernel_classes_Class;
use core_kernel_classes_Resource;
use oat\generis\model\data\event\ResourceCreated;
use oat\oatbox\service\ConfigurableService;
use oat\taoAdvancedSearch\model\Index\Listener\ListenerInterface;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;
use oat\taoAdvancedSearch\model\Index\Service\ResultIndexer;
use oat\taoAdvancedSearch\model\Metadata\Normalizer\MetadataNormalizer;
use oat\taoAdvancedSearch\model\Resource\Service\SyncResourceResultIndexer;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface as SearchEngineIndexerInterface;
use Throwable;

class MetadataInheritanceListener extends ConfigurableService implements ListenerInterface
{
    public const SERVICE_ID = 'taoAdvancedSearch/MetadataInheritanceListener';

    /**
     * @throws UnsupportedEventException
     */
    public function listen($event): void
    {
        if (!$event instanceof ResourceCreated) {
            throw new UnsupportedEventException(ResourceCreated::class);
        }

        if ($event->getResource() instanceof core_kernel_classes_Class) {
            $this->getIndexer()->addIndex($event->getResource());
            return;
        }

        if ($this->isMediaResource($event->getResource())) {
            try {
                $this->getSyncIndexer()->addIndex($event->getResource());
            } catch (Throwable $exception) {
                common_Logger::e(
                    sprintf('Could not sync-index created media resource: %s', $exception->getMessage())
                );
            }
        }
    }

    private function isMediaResource(core_kernel_classes_Resource $resource): bool
    {
        $types = $resource->getTypes();

        if (empty($types)) {
            return false;
        }

        $mediaClass = $resource->getClass(SearchEngineIndexerInterface::MEDIA_CLASS_URI);

        foreach ($types as $type) {
            if ($type->equals($mediaClass) || $type->isSubClassOf($mediaClass)) {
                return true;
            }
        }

        return false;
    }

    private function getIndexer(): IndexerInterface
    {
        $indexer = $this->getServiceLocator()->get(ResultIndexer::class);
        $indexer->setNormalizer(
            $this->getServiceLocator()->get(MetadataNormalizer::class)
        );
        return $indexer;
    }

    private function getSyncIndexer(): IndexerInterface
    {
        return $this->getServiceManager()->get(SyncResourceResultIndexer::class);
    }
}
