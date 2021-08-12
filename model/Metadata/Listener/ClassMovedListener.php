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

use oat\oatbox\service\ConfigurableService;
use oat\tao\model\event\ClassMovedEvent;
use oat\taoAdvancedSearch\model\Index\Listener\ListenerInterface;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;
use oat\taoAdvancedSearch\model\Index\Service\ResultIndexer;
use oat\taoAdvancedSearch\model\Metadata\Normalizer\MetadataNormalizer;

class ClassMovedListener extends ConfigurableService implements ListenerInterface
{
    public const SERVICE_ID = 'taoAdvancedSearch/ClassMovedListener';

    /**
     * @throws UnsupportedEventException
     */
    public function listen($event): void
    {
        if (!$event instanceof ClassMovedEvent) {
            throw new UnsupportedEventException(ClassMovedEvent::class);
        }

        $this->getIndexer()->addIndex(
            $event->getClass()
        );
    }

    private function getIndexer(): IndexerInterface
    {
        /** @var ResultIndexer $indexer */
        $indexer = $this->getServiceLocator()->get(ResultIndexer::class);
        $indexer->setNormalizer(
            $this->getServiceLocator()->get(MetadataNormalizer::class)
        );
        return $indexer;
    }
}
