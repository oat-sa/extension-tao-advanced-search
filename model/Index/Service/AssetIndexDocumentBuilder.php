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
use oat\taoAdvancedSearch\model\SearchEngine\Service\AssetSearchTokenizer;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesDocumentBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Service\NestedAttributesFeature;
use oat\taoMediaManager\model\TaoMediaOntology;

/**
 * Ensures assets index documents store MIME type and RM search tokens.
 */
class AssetIndexDocumentBuilder implements IndexDocumentBuilderInterface
{
    private const ATTRIBUTES_FIELD = 'attributes';

    /** @var IndexDocumentBuilderInterface */
    private $inner;

    /** @var AssetSearchTokenizer */
    private $assetSearchTokenizer;

    /** @var NestedAttributesDocumentBuilder */
    private $nestedAttributesDocumentBuilder;

    /** @var NestedAttributesFeature */
    private $nestedAttributesFeature;

    public function __construct(
        IndexDocumentBuilderInterface $inner,
        ?AssetSearchTokenizer $assetSearchTokenizer = null,
        ?NestedAttributesDocumentBuilder $nestedAttributesDocumentBuilder = null,
        ?NestedAttributesFeature $nestedAttributesFeature = null
    ) {
        $this->inner = $inner;
        $this->assetSearchTokenizer = $assetSearchTokenizer ?? new AssetSearchTokenizer();
        $this->nestedAttributesDocumentBuilder = $nestedAttributesDocumentBuilder;
        $this->nestedAttributesFeature = $nestedAttributesFeature;
    }

    public function createDocumentFromResource(core_kernel_classes_Resource $resource): IndexDocument
    {
        $document = $this->inner->createDocumentFromResource($resource);

        if (!$this->isMediaResource($resource)) {
            return $document;
        }

        $body = $document->getBody();

        $mimeType = $this->resolveMimeType($resource);
        if ($mimeType !== null) {
            $body['mime_type'] = $mimeType;
        }

        $body['resource_id'] = (string) $resource->getUri();

        return new IndexDocument(
            $document->getId(),
            $this->enrichMediaBody($body, $document),
            $document->getIndexProperties(),
            $document->getDynamicProperties(),
            $document->getAccessProperties()
        );
    }

    public function createDocumentFromArray(array $resourceData = []): IndexDocument
    {
        if (isset($resourceData['id']) && is_array($resourceData['body'] ?? null)) {
            $resourceData['body']['resource_id'] = (string) $resourceData['id'];
        }

        $document = $this->inner->createDocumentFromArray($resourceData);

        $body = $document->getBody();

        if (!$this->isMediaBody($body)) {
            return $document;
        }

        if (isset($resourceData['id'])) {
            $body['resource_id'] = (string) $resourceData['id'];
        }

        return new IndexDocument(
            $document->getId(),
            $this->enrichMediaBody($body, $document),
            $document->getIndexProperties(),
            $document->getDynamicProperties(),
            $document->getAccessProperties()
        );
    }

    private function enrichMediaBody(array $body, IndexDocument $document): array
    {
        if (
            $this->nestedAttributesFeature !== null
            && $this->nestedAttributesFeature->shouldUseNestedAttributes(IndexerInterface::ASSETS_INDEX)
        ) {
            $body[self::ATTRIBUTES_FIELD] = $this->getNestedAttributesDocumentBuilder()
                ->buildFromDynamicProperties((array) $document->getDynamicProperties());
        }

        return $this->assetSearchTokenizer->enrichDocumentBody($body);
    }

    private function isMediaBody(array $body): bool
    {
        $parentClasses = $body['parent_classes'] ?? '';
        if (
            is_string($parentClasses)
            && in_array(IndexerInterface::MEDIA_CLASS_URI, explode(';', $parentClasses), true)
        ) {
            return true;
        }

        return in_array(IndexerInterface::MEDIA_CLASS_URI, (array) ($body['type'] ?? []), true);
    }

    private function getNestedAttributesDocumentBuilder(): NestedAttributesDocumentBuilder
    {
        if ($this->nestedAttributesDocumentBuilder === null) {
            throw new \RuntimeException('NestedAttributesDocumentBuilder is not configured');
        }

        return $this->nestedAttributesDocumentBuilder;
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
