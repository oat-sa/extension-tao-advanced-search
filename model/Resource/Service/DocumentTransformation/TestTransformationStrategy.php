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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA.
 */

namespace oat\taoAdvancedSearch\model\Resource\Service\DocumentTransformation;

use core_kernel_classes_Resource;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\TaoOntology;
use Psr\Log\LoggerInterface;
use taoQtiTest_models_classes_QtiTestService;

class TestTransformationStrategy implements DocumentTransformationStrategy
{
    /** @var LoggerInterface */
    private $logger;

    /** @var taoQtiTest_models_classes_QtiTestService */
    private $qtiTestService;

    public function __construct(
        LoggerInterface $logger,
        taoQtiTest_models_classes_QtiTestService $qtiTestService
    ) {
        $this->logger = $logger;
        $this->qtiTestService = $qtiTestService;
    }

    public function transform(
        core_kernel_classes_Resource $resource,
        IndexDocument $indexDocument
    ): IndexDocument {
        $body = $indexDocument->getBody();

        if (!$this->isTestType($body['type'])) {
            return $indexDocument;
        }

        $this->logger->info(
            "Resource is a test, we'll need to extract its related Items"
        );

        // Get the resources associated with the items of the tests. Item URIs
        // come from tao-qtitestdefinition.xml file associated with the test.
        //
        $items = $this->qtiTestService->getItems($resource);

        return $this->addItems($indexDocument, $items);
    }

    private function isTestType($type): bool
    {
        return in_array(
            TaoOntology::CLASS_URI_TEST,
            is_array($type) ? $type : [$type],
            true
        );
    }

    private function addItems(IndexDocument $doc, array $items): IndexDocument
    {
        // IndexDocument is a ValueObject from Core: We need to rebuild it
        // with the additional properties
        //
        $id = $doc->getId();
        $body = $doc->getBody();
        $indexesProperties = $doc->getIndexProperties();
        $accessProperties = $doc->getAccessProperties();
        $dynamicProperties = $doc->getDynamicProperties();

        $this->logger->info(
            sprintf("%s: id: %s", self::class, var_export($id, true))
        );

        // Add a new property for referenced items (in the same level as
        // label, class, parent classes, etc)
        //
        $body['referenced_resources'] = $this->getReferencedResources($items);

        $this->logger->debug(
            sprintf(
                '%s: id=%s new body=%s',
                self::class,
                $id,
                var_export($body, true)
            )
        );

        return new IndexDocument(
            $id,
            $body,
            $indexesProperties,
            $dynamicProperties,
            $accessProperties
        );
    }

    private function getReferencedResources(array $items): array
    {
        $itemURIs = [];
        foreach ($items as $item) {
            assert($item instanceof core_kernel_classes_Resource);

            $itemURIs[] = $item->getUri();
        }

        // Remove duplicates *and* reindex the array to have sequential offsets
        return array_values(array_unique($itemURIs));
    }
}
