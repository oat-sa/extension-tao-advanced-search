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
 * Foundation, Inc., 31 Milk St # 960789 Boston, MA 02196 USA.
 *
 * Copyright (c) 2026 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Index\Service;

use core_kernel_classes_Literal;
use core_kernel_classes_Property;
use core_kernel_classes_Resource;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoMediaManager\model\TaoMediaOntology;

/**
 * Ensures assets index documents store MIME type in the dedicated {@code mime_type} keyword field.
 */
class AssetIndexDocumentBuilder implements IndexDocumentBuilderInterface
{
    /** @var IndexDocumentBuilderInterface */
    private $inner;

    public function __construct(IndexDocumentBuilderInterface $inner)
    {
        $this->inner = $inner;
    }

    public function createDocumentFromResource(core_kernel_classes_Resource $resource): IndexDocument
    {
        $document = $this->inner->createDocumentFromResource($resource);

        if (!$this->isMediaResource($resource)) {
            return $document;
        }

        $mimeType = $this->resolveMimeType($resource);
        if ($mimeType === null) {
            return $document;
        }

        $body = $document->getBody();
        $body['mime_type'] = $mimeType;

        return new IndexDocument(
            $document->getId(),
            $body,
            $document->getIndexProperties(),
            $document->getDynamicProperties(),
            $document->getAccessProperties()
        );
    }

    public function createDocumentFromArray(array $resourceData = []): IndexDocument
    {
        return $this->inner->createDocumentFromArray($resourceData);
    }

    private function resolveMimeType(core_kernel_classes_Resource $resource): ?string
    {
        $value = $resource->getOnePropertyValue(
            new core_kernel_classes_Property(TaoMediaOntology::PROPERTY_MIME_TYPE)
        );

        if (!$value instanceof core_kernel_classes_Literal) {
            return null;
        }

        $mimeType = trim((string)$value);
        if ($mimeType === '') {
            return null;
        }

        return $mimeType;
    }

    private function isMediaResource(core_kernel_classes_Resource $resource): bool
    {
        $mediaClass = $resource->getClass(IndexerInterface::MEDIA_CLASS_URI);

        foreach ($resource->getTypes() as $type) {
            if ($type->equals($mediaClass) || $type->isSubClassOf($mediaClass)) {
                return true;
            }
        }

        return false;
    }
}
