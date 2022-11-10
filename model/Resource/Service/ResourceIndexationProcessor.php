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

namespace oat\taoAdvancedSearch\model\Resource\Service;

use core_kernel_classes_Resource;
use oat\oatbox\service\ServiceManager;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\SearchInterface;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Index\Service\IndexerInterface;
use oat\taoQtiTest\models\QtiTestUtils;
use Psr\Log\LoggerInterface;
use qtism\data\AssessmentTest;
// @todo Add a dependency to taoQtiTest in composer.json
use taoQtiTest_models_classes_QtiTestService;
use Throwable;

/**
 * @todo Find a better name for this?
 *
 * @todo UpdateResourceInIndex from TAO Core calls directly
 *       $this->getSearchProxy()->index(), we'll likely need changes in Core to
 *       call this instead (that task just creates the doc with the document
 *       builder and then passes it to the search proxy)
 */
class ResourceIndexationProcessor implements IndexerInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var IndexDocumentBuilderInterface */
    private $indexDocumentBuilder;

    /** @var SearchInterface */
    private $searchService;

    public function __construct(
        LoggerInterface $logger,
        IndexDocumentBuilderInterface $indexDocumentBuilder,
        SearchInterface $searchService
    ) {
        $this->logger = $logger;
        $this->indexDocumentBuilder = $indexDocumentBuilder;
        $this->searchService = $searchService;
    }

    public function addIndex($resource): void
    {
        $this->logger->info("Hello from ResourceIndexationProcessor");

        try {
            $totalIndexed = $this->searchService->index(
                [
                    $this->getDocumentFor($resource)
                ]
            );

            if ($totalIndexed < 1) {
                $this->logWarning($resource, $totalIndexed);
            }
        } catch (Throwable $exception) {
            $this->logException($resource, $exception);
        }
    }

    private function getDocumentFor(
        core_kernel_classes_Resource $resource
    ): IndexDocument {
        // Index Document Builder is from Core

        $document = $this->indexDocumentBuilder->createDocumentFromResource(
            $resource
        );

        return $this->addRelations($resource, $document);
    }

    private function addRelations(
        core_kernel_classes_Resource $resource,
        IndexDocument $document
    ): IndexDocument
    {
        $this->logger->info("Now we try to get resource relations");

        $body = $document->getBody();
        $this->logger->info("body: ".var_export($body,true));

        if ($this->isTestType($body['type']))
        {
            $this->logger->info(
                "Resource is a test, we'll need to extract its related Items"
            );

            // @todo Move this to a helper method (or, even better, move it
            //       to a strategy class)

            // Get Item IDs stored in the tao-qtitestdefinition.xml file
            // associated with the test
            $items = $this->getQtiTestService()->getItems($resource);

            $itemURIs = [];
            foreach ($items as $assessmentItemRef => $item)
            {
                $this->logger->info(
                    " {$assessmentItemRef} -> item: ".$item->getUri()
                );

                // @todo Pass the item instances themselves to addItemIds and
                //       let it decide how to extract the IDs etc (SRP)
                $itemURIs[] = $item->getUri();
            }

            $document = $this->addItemIds($document, $itemURIs);
        }

        return $document;
    }

    private function addItemIds(IndexDocument $doc, array $ids): IndexDocument
    {
        // IndexDocument is a ValueObject from Core: We need to rebuild it
        // with the additional properties
        //
        $id = $doc->getId();
        $body = $doc->getBody();
        $indexesProperties = $doc->getIndexProperties();
        $accessProperties = $doc->getAccessProperties();
        $dynamicProperties = $doc->getDynamicProperties();

        // @todo Magic goes here

        return new IndexDocument(
            $id,
            $body,
            $indexesProperties,
            $dynamicProperties,
            $accessProperties
        );
    }

    private function isTestType($type): bool
    {
        return (in_array(TaoOntology::CLASS_URI_TEST, $type));
    }

    public function getTestDefinition($qtiTestCompilation): AssessmentTest
    {
        return $this->getQtiTestUtils()->getTestDefinition($qtiTestCompilation);
    }

    private function getQtiTestService(): taoQtiTest_models_classes_QtiTestService
    {
        return $this->getService(taoQtiTest_models_classes_QtiTestService::class);
    }

    private function getQtiTestUtils(): QtiTestUtils
    {
        return $this->getService(QtiTestUtils::SERVICE_ID);
    }

    /**
     * @fixme use DI
     */
    private function getService(string $serviceId)
    {
        return ServiceManager::getServiceManager()->get($serviceId);
    }

    private function logWarning(
        core_kernel_classes_Resource $resource,
        int $totalIndexed
    ): void {
        $this->logger->warning(
            sprintf(
                'Could not index resource %s (%s): totalIndexed=%d',
                $resource->getLabel(),
                $resource->getUri(),
                $totalIndexed
            )
        );
    }

    private function logException(
        core_kernel_classes_Resource $resource,
        Throwable $exception
    ): void {
        $this->logger->error(
            sprintf(
                'Could not index resource %s (%s). Error: %s',
                $resource->getLabel(),
                $resource->getUri(),
                $exception->getMessage()
            )
        );
    }
}
