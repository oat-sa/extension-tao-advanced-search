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

use ArrayIterator;
use Elastic\Elasticsearch\Client;
use Exception;
use Iterator;
use oat\tao\model\search\index\IndexDocument;
use oat\tao\model\search\SearchInterface as TaoSearchInterface;
use oat\tao\model\search\SyntaxException;
use oat\tao\model\search\ResultSet;
use oat\taoAdvancedSearch\model\SearchEngine\AggregationQuery;
use oat\taoAdvancedSearch\model\SearchEngine\AggregationResult;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\SearchInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Normalizer\SearchResultNormalizer;
use oat\taoAdvancedSearch\model\SearchEngine\Query;
use oat\taoAdvancedSearch\model\SearchEngine\SearchResult;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use Psr\Log\LoggerInterface;

class ElasticSearch implements SearchInterface, TaoSearchInterface
{
    /** @var string */
    private $indexFile;

    /** @var Client */
    private $client;

    /** @var QueryBuilder */
    private $queryBuilder;

    /** @var IndexerInterface */
    private $indexer;

    /** @var IndexPrefixer */
    private $prefixer;

    /** @var LoggerInterface */
    private $logger;

    /** @var SearchResultNormalizer */
    private $searchResultNormalizer;

    public function __construct(
        Client $client,
        QueryBuilder $queryBuilder,
        IndexerInterface $indexer,
        IndexPrefixer $prefixer,
        LoggerInterface $logger,
        SearchResultNormalizer $searchResultNormalizer
    ) {
        $this->client = $client;
        $this->queryBuilder = $queryBuilder;
        $this->indexer = $indexer;
        $this->prefixer = $prefixer;
        $this->logger = $logger;
        $this->searchResultNormalizer = $searchResultNormalizer;
    }

    public function setIndexFile(string $indexFile): void
    {
        $this->indexFile = $indexFile;
    }

    public function countDocuments(string $index): int
    {
        $result = $this->client->count(
            [
                'index' => $this->prefixer->prefix($index),
            ]
        );

        return $result['count'] ?? 0;
    }

    public function search(Query $query): SearchResult
    {
        $query = [
            'index' => $this->prefixer->prefix($query->getIndex()),
            'body' => json_encode(
                [
                    'query' => [
                        'query_string' => [
                            'default_operator' => 'AND',
                            'query' => $query->getQueryString()
                        ]
                    ],
                    'size' => $query->getLimit(),
                    'from' => $query->getOffset(),
                    'sort' => [],
                ]
            )
        ];

        return $this->buildResultSet($this->client->search($query)->asArray());
    }

    public function aggregate(AggregationQuery $aggQuery): ResultSet
    {
        $query = [
            'index' => $this->prefixer->prefix($aggQuery->getQuery()->getIndex()),
            'body' => json_encode(
                [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'terms' => $aggQuery->getTerms()
                                ]
                            ]
                        ]
                    ],
                    'size' => 0,
                    'aggs' => $aggQuery->getAggregations()
                ]
            )
        ];

        return $this->buildAggregationSet($this->client->search($query)->asArray());
    }

    public function query($queryString, $type, $start = 0, $count = 10, $order = '_id', $dir = 'DESC'): ResultSet
    {
        if ($order == 'id') {
            $order = '_id';
        }

        try {
            $query = $this->queryBuilder->getSearchParams($queryString, $type, $start, $count, $order, $dir);
            $this->logger->debug(sprintf('Elasticsearch Query %s', json_encode($query)));

            return $this->searchResultNormalizer->normalizeByByResultSet(
                $this->buildResultSet($this->client->search($query)->asArray())
            );
        } catch (Exception $exception) {
            switch ($exception->getCode()) {
                case 400:
                    $json = json_decode($exception->getMessage(), true);
                    $message = __(
                        'There is an error in your search query, system returned: %s',
                        $json['error']['reason'] ?? ''
                    );
                    $this->logger->error(sprintf('Elasticsearch: %s %s', $message, $exception->getMessage()));
                    throw new SyntaxException($queryString, $message);
                default:
                    $message = 'An unknown error occurred during search';
                    $this->logger->error(sprintf('Elasticsearch: %s "%s"', $message, $exception->getMessage()));
                    throw new SyntaxException($queryString, __($message));
            }
        }
    }

    public function index($documents = []): int
    {
        $documents = $documents instanceof Iterator
            ? $documents
            : new ArrayIterator($documents);

        foreach ($documents as $document) {
            if (!$document instanceof IndexDocument) {
                throw new \InvalidArgumentException(
                    'Expected an instance of IndexDocument, got ' . get_class($document)
                );
            }
            $indexName = $this->indexer->getIndexNameByDocument($document);
            if (!$this->isExistingIndex($indexName)) {
                $this->createIndexes();
                continue;
            }
        }

        return $this->indexer->buildIndex($documents);
    }

    public function remove($resourceId): bool
    {
        return $this->indexer->deleteDocument((string)$resourceId);
    }

    public function supportCustomIndex(): bool
    {
        return true;
    }

    public function createIndexes(): void
    {
        $definitions = $this->getIndexes();
        foreach ($definitions as $def) {
            $name = $this->prefixer->prefix($def['index']);

            if ($this->isExistingIndex($name)) {
                continue;
            }

            $payload = $def;
            $payload['index'] = $name;

            // 3) Create it
            $this->client
                 ->indices()
                 ->create($payload);
        }
    }

    public function updateAliases(): void
    {
        $indexes = $this->getIndexes();

        $aliases = [];

        foreach ($indexes as $index) {
            $indexName = $this->prefixer->prefix($index['index']);
            $aliases[$indexName] = current(array_keys($index['body']['aliases']));
        }
        $this->client->indices()->updateAliases(
            [
                'body' => [
                    'actions' => array_map(
                        function ($index, $alias) {
                            return [
                                'add' => [
                                    'index' => $index,
                                    'alias' => $alias
                                ]
                            ];
                        },
                        array_keys($aliases),
                        $aliases
                    )
                ]
            ]
        );
    }

    public function flush(): array
    {
        $indexes = $this->prefixer->prefixAll(IndexerInterface::AVAILABLE_INDEXES);
        $indexList = implode(',', $indexes);

        $params = [
            'index'               => $indexList,
            'ignore_unavailable'  => true,
            'allow_no_indices'    => true,
        ];

        return $this->client->indices()
                            ->delete($params)
                            ->asArray();
    }

    public function ping(): bool
    {
        return $this->client->ping()->asBool();
    }

    private function isExistingIndex(string $indexName): bool
    {
        try {
            return $this->client->indices()->exists(['index' => $indexName])->asBool();
        } catch (Exception $e) {
            $this->logger->error(sprintf('Error checking index existence: %s', $e->getMessage()));
            return false;
        }
    }

    private function buildResultSet(array $elasticResult = []): SearchResult
    {
        $uris = [];
        $total = 0;

        if ($elasticResult && isset($elasticResult['hits'])) {
            foreach ($elasticResult['hits']['hits'] as $document) {
                $document['_source']['id'] = $document['_id'];
                $uris[] = $document['_source'];
            }

            $total = is_array($elasticResult['hits']['total'])
                ? $elasticResult['hits']['total']['value']
                : $elasticResult['hits']['total'];
        }

        return new SearchResult($uris, $total);
    }

    private function buildAggregationSet(array $elasticResult): AggregationResult
    {
        $total = 0;
        $uris = [];

        if (is_array($elasticResult) && !empty($elasticResult) && $this->hasHitsDefined($elasticResult)) {
            $total = $elasticResult['hits']['total']['value'];
        }

        foreach ($elasticResult['aggregations']['matching_uris']['buckets'] as $bucket) {
            $uris[] = $bucket['key'];
        }

        return new AggregationResult($uris, $total);
    }

    public function __toPhpCode()
    {
        return __CLASS__;
    }

    private function getIndexes(): array
    {
        $indexFile = $this->getIndexFile();
        $indexes = ($indexFile && is_readable($indexFile))
                     ? require $indexFile
                     : [];
        return $indexes;
    }

    private function getIndexFile(): string
    {
        return $this->indexFile ?? __DIR__ .
        DIRECTORY_SEPARATOR .
        '..' .
        DIRECTORY_SEPARATOR .
        '..' .
        DIRECTORY_SEPARATOR .
        '..' .
        DIRECTORY_SEPARATOR .
        '..' .
        DIRECTORY_SEPARATOR .
        'config' .
        DIRECTORY_SEPARATOR .
        'index.conf.php';
    }

    private function hasHitsDefined(array $elasticResult): bool
    {
        return isset(
            $elasticResult['hits'],
            $elasticResult['hits']['total'],
            $elasticResult['hits']['total']['value']
        );
    }
}
