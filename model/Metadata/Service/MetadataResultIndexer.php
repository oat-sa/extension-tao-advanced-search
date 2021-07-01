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

namespace oat\taoAdvancedSearch\model\Metadata\Service;

use oat\oatbox\service\ConfigurableService;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;
use oat\taoAdvancedSearch\model\Index\Service\NormalizerAwareInterface;
use oat\taoAdvancedSearch\model\Metadata\Cache\ClassMetadataCache;

class MetadataResultIndexer extends ConfigurableService implements IndexerInterface, NormalizerAwareInterface
{
    /** @var NormalizerInterface */
    private $normalizer;

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->normalizer = $normalizer;
    }

    public function addIndex($resource): void
    {
        $normalizedResource = $this->normalizer->normalize($resource);

        $this->getCache()->store(
            $normalizedResource->getId(),
            $normalizedResource->getData()
        );
    }

    private function getCache(): ClassMetadataCache
    {
        return $this->getServiceLocator()->get(ClassMetadataCache::class);
    }
}
