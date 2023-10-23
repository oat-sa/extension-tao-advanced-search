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
 * Copyright (c) 2021-2023 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Index\Service;

use common_Exception;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\SearchInterface;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;
use Throwable;

class SyncResultIndexer extends ConfigurableService implements IndexerInterface, NormalizerAwareInterface
{
    /** @var NormalizerInterface */
    private $normalizer;

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->normalizer = $normalizer;
    }

    /**
     * @throws Throwable
     * @throws common_Exception
     */
    public function addIndex($resource): void
    {
        $normalizedResource = $this->normalizer->normalize($resource);

        try {
            $document = $this->getIndexDocumentBuilder()->createDocumentFromArray(
                [
                    'id' => $normalizedResource->getId(),
                    'body' => $normalizedResource->getData()
                ]
            );
        } catch (Throwable $e) {
            $this->logWarning(
                sprintf(
                    '%s: Caught %s on call to createDocumentFromArray: %s (resourceId: %s, label: %s)',
                    self::class,
                    get_class($e),
                    $e->getMessage(),
                    $normalizedResource->getId(),
                    $normalizedResource->getLabel()
                )
            );

            throw $e;
        }

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

    private function getIndexDocumentBuilder(): IndexDocumentBuilderInterface
    {
        return $this->getServiceLocator()->getContainer()->get(AdvancedSearchIndexDocumentBuilder::class);
    }
}
