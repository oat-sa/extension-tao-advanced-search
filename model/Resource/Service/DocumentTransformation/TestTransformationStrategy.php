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
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\SearchInterface;
use oat\tao\model\TaoOntology;
use Psr\Log\LoggerInterface;
use taoQtiTest_models_classes_QtiTestService;
use ArrayIterator;
use Iterator;

class TestTransformationStrategy implements DocumentTransformationStrategy
{
    /** @var LoggerInterface */
    private $logger;

    /** @var IndexDocumentBuilderInterface */
    private $indexDocumentBuilder; // @todo It may be removed

    /** @var SearchInterface */
    private $searchService; // @todo It may be removed

    /** @var taoQtiTest_models_classes_QtiTestService */
    private $qtiTestService;

    public function __construct(
        LoggerInterface $logger,
        IndexDocumentBuilderInterface $indexDocumentBuilder,
        SearchInterface $searchService,
        taoQtiTest_models_classes_QtiTestService $qtiTestService
    ) {
        $this->logger = $logger;
        $this->indexDocumentBuilder = $indexDocumentBuilder;
        $this->searchService = $searchService;
        $this->qtiTestService = $qtiTestService;
    }

    public function transform(
        core_kernel_classes_Resource $resource,
        IndexDocument $indexDocument
    ): IndexDocument {
        $body = $indexDocument->getBody();

        $this->logger->info("body: ".var_export($body,true));

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
        $this->logger->info(
            sprintf("%s: body: %s", self::class, var_export($body, true))
        );
        $this->logger->info(
            sprintf("%s: idxProp: %s",
                self::class, var_export($indexesProperties, true)
            )
        );
        $this->logger->info(
            sprintf("%s: accessProp: %s",
                self::class, var_export($accessProperties, true)
            )
        );
        $this->logger->info(
            sprintf("%s: dynProp: %s",
                self::class, var_export($dynamicProperties, true)
            )
        );

        // Add a new property for referenced items (in the same level as
        // label, class, parent classes, etc)
        //
        $body['referenced_resources'] = $this->getReferencedResources($items);

        $this->logger->info(
            sprintf(
                '%s: referenced_resources = %s',
                self::class,
                var_export($body['referenced_resources'], true)
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

            $this->logger->info("item: ".$item->getUri());
            $itemURIs[] = $item->getUri();
        }

        $this->logger->info("before: ". var_export($itemURIs,true));

        // Remove duplicates *and* reindex the array to have sequential offsets
        $itemURIs = array_values(array_unique($itemURIs));
        $this->logger->info("after:  ". var_export($itemURIs,true));

        return $itemURIs;
    }
}
