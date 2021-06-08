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

namespace oat\taoAdvancedSearch\model\Index\Service;

use oat\oatbox\service\ConfigurableService;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\search\tasks\AddSearchIndexFromArray;
use oat\tao\model\taskQueue\QueueDispatcherInterface;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;

class ResultIndexer extends ConfigurableService implements IndexerInterface
{
    /** @var NormalizerInterface */
    private $normalizer;

    public function setNormalizer(NormalizerInterface $normalizer): self
    {
        $this->normalizer = $normalizer;

        return $this;
    }

    public function addIndex($resource): void
    {
        if (!$this->getServiceLocator()->get(AdvancedSearchChecker::class)->isEnabled()) {
            return;
        }
        $normalizedResource = $this->normalizer->normalize($resource);

        $this->getQueueDispatcher()->createTask(
            new AddSearchIndexFromArray(),
            [
                $normalizedResource->getId(),
                $normalizedResource->getData()
            ],
            __('Adding/Updating search index for %s', $normalizedResource->getLabel())
        );
    }

    private function getQueueDispatcher(): QueueDispatcherInterface
    {
        return $this->getServiceLocator()->get(QueueDispatcherInterface::SERVICE_ID);
    }
}
