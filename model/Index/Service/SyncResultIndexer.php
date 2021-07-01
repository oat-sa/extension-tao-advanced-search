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
use oat\tao\model\search\index\IndexService;
use oat\tao\model\search\SearchInterface;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;

class SyncResultIndexer extends ConfigurableService implements IndexerInterface, NormalizerAwareInterface
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

        $document = $this->getIndexerService()->getDocumentBuilder()->createDocumentFromArray(
            [
                'id' => $normalizedResource->getId(),
                'body' => $normalizedResource->getData()
            ]
        );

        $this->getSearch()->index(
            [
                $document
            ]
        );
    }

    private function getSearch(): SearchInterface
    {
        return $this->getServiceLocator()->get(SearchProxy::SERVICE_ID);
    }

    private function getIndexerService(): IndexService
    {
        return $this->getServiceLocator()->get(IndexService::SERVICE_ID);
    }
}
