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

namespace oat\taoAdvancedSearch\tests\Unit\Index\Service;

use core_kernel_classes_Class;
use core_kernel_classes_Literal;
use core_kernel_classes_Property;
use core_kernel_classes_Resource;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\taoAdvancedSearch\model\Index\Service\AssetIndexDocumentBuilder;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoMediaManager\model\TaoMediaOntology;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AssetIndexDocumentBuilderTest extends TestCase
{
    /** @var IndexDocumentBuilderInterface|MockObject */
    private $inner;

    /** @var AssetIndexDocumentBuilder */
    private $subject;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(IndexDocumentBuilderInterface::class);
        $this->subject = new AssetIndexDocumentBuilder($this->inner);
    }

    public function testCreateDocumentFromResourceSetsMimeTypeForMediaAssets(): void
    {
        $resource = $this->createMediaResource('image/png');

        $this->inner
            ->expects($this->once())
            ->method('createDocumentFromResource')
            ->with($resource)
            ->willReturn(
                new IndexDocument(
                    'media-uri',
                    ['type' => ['http://ontology/class'], 'label' => 'photo.png'],
                    [],
                    null,
                    null
                )
            );

        $document = $this->subject->createDocumentFromResource($resource);

        $this->assertSame('image/png', $document->getBody()['type']);
        $this->assertSame('photo.png', $document->getBody()['label']);
    }

    public function testCreateDocumentFromResourceLeavesNonMediaDocumentsUntouched(): void
    {
        $resource = $this->createMock(core_kernel_classes_Resource::class);
        $genericClass = $this->createMock(core_kernel_classes_Class::class);
        $mediaClass = $this->createMock(core_kernel_classes_Class::class);

        $resource->method('getClass')->with(IndexerInterface::MEDIA_CLASS_URI)->willReturn($mediaClass);
        $resource->method('getTypes')->willReturn([$genericClass]);
        $genericClass->method('equals')->willReturn(false);
        $genericClass->method('isSubClassOf')->willReturn(false);

        $expected = new IndexDocument('item-uri', ['type' => ['item-class']], [], null, null);
        $this->inner->method('createDocumentFromResource')->willReturn($expected);

        $this->assertSame($expected, $this->subject->createDocumentFromResource($resource));
    }

    private function createMediaResource(string $mimeType): core_kernel_classes_Resource
    {
        $resource = $this->createMock(core_kernel_classes_Resource::class);
        $mediaClass = $this->createMock(core_kernel_classes_Class::class);
        $mediaType = $this->createMock(core_kernel_classes_Class::class);

        $resource->method('getClass')->with(IndexerInterface::MEDIA_CLASS_URI)->willReturn($mediaClass);
        $resource->method('getTypes')->willReturn([$mediaType]);
        $mediaType->method('equals')->with($mediaClass)->willReturn(true);
        $resource
            ->method('getOnePropertyValue')
            ->with(new core_kernel_classes_Property(TaoMediaOntology::PROPERTY_MIME_TYPE))
            ->willReturn(new core_kernel_classes_Literal($mimeType));

        return $resource;
    }
}
