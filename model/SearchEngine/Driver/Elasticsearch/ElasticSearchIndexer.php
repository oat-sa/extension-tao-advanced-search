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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch;

use oat\oatbox\service\ServiceManager;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilder;
use oat\tao\model\search\index\IndexDocument;
use Elasticsearch\Client;
use oat\tao\model\search\SearchInterface;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use Psr\Log\LoggerInterface;
use Exception;
use Iterator;
use RuntimeException;
use taoQtiTest_models_classes_QtiTestService;
use Throwable;

class ElasticSearchIndexer implements IndexerInterface
{
    use LogIndexOperationsTrait;

    private const INDEXING_BLOCK_SIZE = 100;

    /** @var Client */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    /** @var IndexPrefixer */
    private $prefixer;

    public function __construct(Client $client, LoggerInterface $logger, IndexPrefixer $prefixer)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->prefixer = $prefixer;
    }

    public function getIndexNameByDocument(IndexDocument $document): string
    {
        $documentBody = $document->getBody();

        if (!isset($documentBody['type'])) {
            throw new RuntimeException('type property is undefined on the document');
        }

        $documentType = is_string($documentBody['type']) ? [$documentBody['type']] : $documentBody['type'];

        foreach (IndexerInterface::AVAILABLE_INDEXES as $ontology => $indexName) {
            if (in_array($ontology, $documentType)) {
                return $this->prefixer->prefix($indexName);
            }
        }

        return IndexerInterface::UNCLASSIFIEDS_DOCUMENTS_INDEX;
    }

    public function buildIndex(Iterator $documents): int
    {
        $visited = 0;
        $skipped = 0;
        $exceptions = 0;
        $count = 0;
        $blockSize = 0;
        $params = [];

        while ($documents->valid()) {
            /** @var IndexDocument $document */
            $document = $documents->current();
            $visited++;

            try {
                $indexName = $this->getIndexNameByDocument($document);
            } catch (Exception $e) {
                $this->logIndexFailure($this->logger, $e, __METHOD__);
                $exceptions++;

                continue;
            }

            if ($indexName === IndexerInterface::UNCLASSIFIEDS_DOCUMENTS_INDEX) {
                $this->logUnclassifiedDocument(
                    $this->logger,
                    $document,
                    __METHOD__,
                    $indexName
                );

                $this->logMappings($this->logger, $document);

                $documents->next();
                $skipped++;

                continue;
            }

            $this->logAddingDocumentToQueue($this->logger, $document, $indexName);
            $params = $this->extendBatch('index', $indexName, $document, $params);

            $documents->next();

            $blockSize++;

            if ($blockSize === self::INDEXING_BLOCK_SIZE) {
                $this->logBatchFlush($this->logger, __METHOD__, $params);

                $response = $this->client->bulk($params);
                $this->logErrorsFromResponse(
                    $this->logger,
                    $document,
                    $response
                );

                $count += $blockSize;
                $blockSize = 0;
                $params = [];
            }
        }

        if ($blockSize > 0) {
            $this->logBatchFlush($this->logger, __METHOD__, $params);

            $response = $this->client->bulk($params);
            $this->logErrorsFromResponse($this->logger, null, $response);

            $count += $blockSize;
        }

        $this->logCompletion($this->logger, $count, $visited, $skipped, $exceptions);

        return $count;
    }

    public function deleteDocument(string $id): bool
    {
        $document = $this->searchResourceByIds([$id]);

        if ($document) {
            $deleteParams = [
                'type' => '_doc',
                'index' => $document['_index'],
                'id' => $document['_id']
            ];

            try {
                $this->client->delete($deleteParams);

                $this->logger->debug(sprintf('[documentId: "%s"] ', $id));
            } catch (Throwable $e) {
                $this->logDocumentFailure(
                    $this->logger,
                    $e,
                    __METHOD__,
                    $document,
                    $id,
                    $deleteParams
                );
            }

            return true;
        }

        $this->logger->info(sprintf('Document to delete not found: %s', $id));

        return false;
    }

    public function searchResourceByIds(array $ids): array
    {
        $searchParams = [
            'body' => [
                'query' => [
                    'ids' => [
                        'values' => $ids
                    ]
                ]
            ]
        ];
        $response = $this->client->search($searchParams);
        $hits = $response['hits'] ?? [];

        $document = [];
        if ($hits && isset($hits['hits'], $hits['total'])) {
            $document = current($hits['hits']) ?: [];
        }

        return $document;
    }

    private function extendBatch(
        string $action,
        string $indexName,
        IndexDocument $document,
        array $params
    ): array {
        $this->logger->debug(
            sprintf(
                "%s::extendBatch: input = %s",
                self::class,
                var_export($document, true)
            )
        );

        // Apply transformations
        // @todo IoD
        //
        /*$transformations = [
            new TestTransformationStrategy(
                $this->logger,
                $this->getQtiTestService()
            )
        ];

        $resource = new \core_kernel_classes_Resource($document->getId());

        foreach ($transformations as $strategy) {
            $this->logger->critical(
                sprintf('Applying transformation: %s', get_class($strategy))
            );

            $document = $strategy->transform($resource, $document);
        }*/

        // @todo Find a way to include the new fields here (or check if
        //       other layers have already added them)

        $params['body'][] = [
            $action => [
                '_index' => $indexName,
                '_id' => $document->getId()
            ]
        ];

        if ('delete' === $action) {
            return $params;
        }

        $body = array_merge(
            $document->getBody(),
            (array)$document->getDynamicProperties(),
            (array)$document->getAccessProperties()
        );

        if ($action === 'update') {
            $body = ['doc' => $body];
        }

        $this->logger->debug(
            sprintf(
                "%s::extendBatch: body = %s",
                self::class,
                var_export($body, true)
            )
        );

        $params['body'][] = $body;

        return $params;
    }

    /*private function getQtiTestService(): taoQtiTest_models_classes_QtiTestService
    {
        return $this->getService(taoQtiTest_models_classes_QtiTestService::class);
    }*/

    /**
     * @fixme use DI
     */
    /*private function getService(string $serviceId)
    {
        return ServiceManager::getServiceManager()->get($serviceId);
    }

    private function getDocumentBuilder(): IndexDocumentBuilder
    {
        return $this->getService(IndexDocumentBuilder::class);
    }

    private function getSearchService(): SearchInterface
    {
        return $this->getService(SearchProxy::SERVICE_ID);
    }*/
}
