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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Test\Normalizer;

use core_kernel_classes_Resource;
use Exception;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use taoQtiTest_models_classes_QtiTestService as QtiTestService;

class TestNormalizer
{
    private const QTI_IDENTIFIER_KEY = 'qti_identifier';
    private const TEST_QTI_STRUCTURE_KEY = 'test_qti_structure';
    private const ITEMS_KEY = 'item_uris';
    private const ALLOWED_TEST_QTI_STRUCTURE_KEYS = [
        'qti-type',
        'identifier',
        'testParts',
        'navigationMode',
        'assessmentSections',
        'sectionParts',
        'href',
        'categories',
        'timeLimits',
        'maxTime'
    ];

    private QtiTestService $qtiTestService;
    private IndexDocumentBuilderInterface $legacyDocumentBuilder;

    public function __construct(QtiTestService $qtiTestService, IndexDocumentBuilderInterface $legacyDocumentBuilder)
    {
        $this->qtiTestService = $qtiTestService;
        $this->legacyDocumentBuilder = $legacyDocumentBuilder;
    }

    public function normalize(core_kernel_classes_Resource $resource): IndexDocument
    {
        $document = $this->legacyDocumentBuilder->createDocumentFromResource($resource);
        $jsonData = json_decode($this->qtiTestService->getJsonTest($resource), true);

        $body = $document->getBody();
        $body[self::ITEMS_KEY] = $this->getResourceURIsForTestItems($resource);
        $body[self::TEST_QTI_STRUCTURE_KEY] = $this->cleanUpOriginalQtiStructure($jsonData);
        $body[self::QTI_IDENTIFIER_KEY] = $jsonData['identifier'] ?? null;

        return new IndexDocument(
            $document->getId(),
            $body,
            $document->getIndexProperties(),
            $document->getDynamicProperties(),
            $document->getAccessProperties()
        );
    }

    /**
     * IMPORTANT: Do not modify the original QTI structure, except by removing unnecessary keys
     */
    private function cleanUpOriginalQtiStructure(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $data[$key] = $this->cleanUpOriginalQtiStructure($value);
            }

            if (!is_integer($key) && !in_array($key, self::ALLOWED_TEST_QTI_STRUCTURE_KEYS, true)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    private function getResourceURIsForTestItems(core_kernel_classes_Resource $resource): array
    {
        $itemURIs = [];

        foreach ($this->qtiTestService->getItems($resource) as $item) {
            assert($item instanceof core_kernel_classes_Resource);

            $itemURIs[$item->getUri()] = $item->getUri();
        }

        return array_values($itemURIs);
    }
}
