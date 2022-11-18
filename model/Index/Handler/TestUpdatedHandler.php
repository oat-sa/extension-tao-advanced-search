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

use common_Exception;
use common_exception_Error;
use core_kernel_classes_Resource;
use oat\oatbox\event\Event;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\SearchInterface;
use oat\taoQtiTest\models\event\QtiTestImportEvent;
use oat\taoTests\models\event\TestImportEvent;
use oat\taoTests\models\event\TestUpdatedEvent;
use Psr\Log\LoggerInterface;
use RuntimeException;
use taoQtiTest_models_classes_QtiTestService;
use taoQtiTest_models_classes_QtiTestServiceException;

class TestUpdatedHandler extends AbstractEventHandler
{
    /** @var taoQtiTest_models_classes_QtiTestService */
    private $qtiTestService;

    public function __construct(
        LoggerInterface $logger,
        IndexDocumentBuilderInterface $indexDocumentBuilder,
        SearchInterface $searchService,
        taoQtiTest_models_classes_QtiTestService $qtiTestService
    ) {
        parent::__construct(
            $logger,
            $indexDocumentBuilder,
            $searchService,
            [
                QtiTestImportEvent::class, // @todo Add it to the unit tests
                TestUpdatedEvent::class,
            ]
        );

        $this->qtiTestService = $qtiTestService;
    }

    /**
     * @throws common_exception_Error
     */
    protected function getResource(Event $event): core_kernel_classes_Resource
    {
        /** @var $event TestImportEvent|TestUpdatedEvent */
        $eventData = json_decode(json_encode($event->jsonSerialize()));

        if (empty($eventData->testUri)) {
            throw new RuntimeException('Missing testUri');
        }

        return new core_kernel_classes_Resource($eventData->testUri);
    }

    /**
     * @throws common_Exception
     * @throws taoQtiTest_models_classes_QtiTestServiceException
     */
    protected function doHandle(
        Event $event,
        core_kernel_classes_Resource $resource
    ): void {
        $doc = $this->getDocumentFor($resource);
        $totalIndexed = $this->searchService->index([$doc]);

        $this->logger->critical(
            sprintf(
                "Indexed document for test %s: %s",
                $resource->getUri(),
                var_export($doc,true)
            )
        );

        if ($totalIndexed < 1) {
            $this->logResourceNotIndexed($resource, $totalIndexed);
        }

        // When uncommenting this there is another layer (ResourceWatcher) that
        // wipes out the "referenced_resources"
        // die("STOP");
    }

    /**
     * @throws common_Exception
     * @throws taoQtiTest_models_classes_QtiTestServiceException
     */
    private function getDocumentFor(
        core_kernel_classes_Resource $resource
    ): IndexDocument {
        $document = $this->indexDocumentBuilder->createDocumentFromResource(
            $resource
        );

        // IndexDocument is a ValueObject from Core: We need to rebuild it with
        // the updated values. Note also that if the resource is not a test,
        // QtiTestService will throw an exception.
        //
        $body = $document->getBody();
        $body['referenced_resources'] = $this->getReferencedResources(
            $this->qtiTestService->getItems($resource)
        );

        return new IndexDocument(
            $document->getId(),
            $body,
            $document->getIndexProperties(),
            $document->getDynamicProperties(),
            $document->getAccessProperties()
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
