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

namespace oat\taoAdvancedSearch\model\Index\Handler;

use common_exception_Error;
use core_kernel_classes_Resource;
use oat\oatbox\event\Event;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\SearchInterface;
use oat\taoAdvancedSearch\model\Metadata\Listener\UnsupportedEventException;
use oat\taoTests\models\event\TestUpdatedEvent;
use Psr\Log\LoggerInterface;
use taoQtiTest_models_classes_QtiTestService;

class TestUpdatedHandler implements EventHandlerInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var SearchInterface */
    private $searchService;

    /** @var IndexDocumentBuilderInterface */
    private $indexDocumentBuilder;

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

    /**
     * @throws UnsupportedEventException
     * @throws common_exception_Error
     */
    public function handle(Event $event): void
    {
        $this->logger->info(self::class.' called');

        $this->assertIsTestUpdatedEvent($event);

        // @todo Use DI for all services

        $this->addIndex(self::getResourceForEvent($event));
    }

    public function addIndex($resource): void
    {
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
        $this->logger->debug(
            sprintf(
                'Preparing data to index, resource = %s',
                $resource->getUri()
            )
        );

        // Index Document Builder is from Core
        $document = $this->indexDocumentBuilder->createDocumentFromResource(
            $resource
        );

        return $this->transform($resource, $document);
    }

    private function transform(
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

    // @todo Maybe we can just let qtiTestService to decide if it is in fact a test or not
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

    /**
     * @throws UnsupportedEventException
     */
    private function assertIsTestUpdatedEvent(Event $event): void
    {
        if (!($event instanceof TestUpdatedEvent)) {
            throw new UnsupportedEventException(TestUpdatedEvent::class);
        }
    }

    /**
     * @throws common_exception_Error
     */
    private static function getResourceForEvent(TestUpdatedEvent $event)
                                                : core_kernel_classes_Resource
    {
        $eventData = json_decode(json_encode($event->jsonSerialize()));

        if (empty($eventData->testUri)) {
            throw new RuntimeException('Missing testUri');
        }

        return new core_kernel_classes_Resource($eventData->testUri);
    }
}
