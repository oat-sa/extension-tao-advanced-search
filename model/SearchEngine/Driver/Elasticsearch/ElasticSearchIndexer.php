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

use oat\tao\model\search\index\DocumentBuilder\PropertyIndexReferenceFactory;
use oat\tao\model\search\index\IndexDocument;
use Elastic\Elasticsearch\Client;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use Psr\Log\LoggerInterface;
use Exception;
use Iterator;
use RuntimeException;
use Throwable;

class ElasticSearchIndexer implements IndexerInterface
{
    use LogIndexOperationsTrait;

    private const INDEXING_BLOCK_SIZE = 100;
    private const ATTRIBUTES_FIELD = 'attributes';
    private const INDEXES_USING_NESTED_ATTRIBUTES = [
        IndexerInterface::ITEMS_INDEX,
        IndexerInterface::TESTS_INDEX,
        IndexerInterface::DELIVERIES_INDEX,
        IndexerInterface::GROUPS_INDEX,
        IndexerInterface::ASSETS_INDEX,
        IndexerInterface::TEST_TAKERS_INDEX,
    ];

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
                $this->logErrorsFromResponse($this->logger, $document, $response);

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

    private function extendBatch(string $action, string $indexName, IndexDocument $document, array $params): array
    {
        $params['body'][] = [
            $action => [
                '_index' => $indexName,
                '_id' => $document->getId()
            ]
        ];

        if ('delete' === $action) {
            return $params;
        }

        $body = $document->getBody();
        $dynamicProperties = (array) $document->getDynamicProperties();
        if ($this->shouldUseNestedAttributes($indexName)) {
            $body[self::ATTRIBUTES_FIELD] = $this->buildAttributes($dynamicProperties);
        } else {
            $body = array_merge($body, $dynamicProperties);
        }

        $body = array_merge($body, (array) $document->getAccessProperties());

        if ($action === 'update') {
            $body = ['doc' => $body];
        }

        $params['body'][] = $body;

        return $params;
    }

    private function shouldUseNestedAttributes(string $indexName): bool
    {
        foreach (self::INDEXES_USING_NESTED_ATTRIBUTES as $index) {
            if ($indexName === $index || str_ends_with($indexName, $index)) {
                return true;
            }
        }

        return false;
    }

    private function buildAttributes(array $dynamicProperties): array
    {
        $attributes = [];
        $rawSuffix = PropertyIndexReferenceFactory::RAW_SUFFIX;

        foreach ($dynamicProperties as $fieldName => $values) {
            if (!is_array($values) || strpos($fieldName, '_') === false) {
                continue;
            }

            [$type, $key] = explode('_', $fieldName, 2);
            if ($key === '' || substr($key, -strlen($rawSuffix)) === $rawSuffix) {
                continue;
            }

            $rawFieldName = $fieldName . $rawSuffix;
            $rawValues = [];
            if (isset($dynamicProperties[$rawFieldName]) && is_array($dynamicProperties[$rawFieldName])) {
                $rawValues = array_values($dynamicProperties[$rawFieldName]);
            }

            $stringValues = array_map(static fn ($v): string => (string) $v, array_values($values));
            if ($stringValues === []) {
                continue;
            }

            $row = [
                'key' => $key,
                'type' => $type,
                'value' => $stringValues,
            ];

            $rawPayload = $this->resolveRawValuesForIndexedAttribute($rawValues, $stringValues);
            if ($rawPayload !== null) {
                $row['raw_value'] = $rawPayload;
            }

            $attributes[] = $row;
        }

        return $attributes;
    }

    /**
     * Aligns parallel {@see PropertyIndexReferenceFactory::createRaw} values with indexed {@code value} entries.
     * When only one raw entry exists (e.g. multi-select labels imploded into one string), it is stored once on the
     * same nested object as the full {@code value} array.
     *
     * @param list<string> $rawValues
     * @param list<string> $stringValues
     * @return list<string>|string|null
     */
    private function resolveRawValuesForIndexedAttribute(array $rawValues, array $stringValues): array|string|null
    {
        if ($rawValues === []) {
            return null;
        }

        if (count($rawValues) === count($stringValues)) {
            $parts = array_map(static fn ($v): string => (string) $v, array_values($rawValues));

            return count($parts) === 1 ? $parts[0] : $parts;
        }

        if (count($rawValues) === 1) {
            return (string) $rawValues[0];
        }

        return null;
    }
}
