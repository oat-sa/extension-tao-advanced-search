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
use Elasticsearch\Client;
use Exception;
use Iterator;
use \oat\tao\model\search\SearchInterface as TaoSearchInterface;
use oat\tao\model\search\SyntaxException;
use oat\tao\model\search\ResultSet;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\IndexerInterface;
use oat\taoAdvancedSearch\model\SearchEngine\Contract\SearchInterface;
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

    public function __construct(
        Client $client,
        QueryBuilder $queryBuilder,
        IndexerInterface $indexer,
        IndexPrefixer $prefixer,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->queryBuilder = $queryBuilder;
        $this->indexer = $indexer;
        $this->prefixer = $prefixer;
        $this->logger = $logger;
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

        $results = $this->buildResultSet($this->client->search($query));

        return new SearchResult($results->getArrayCopy(), $results->getTotalCount());
    }

    public function query($queryString, $type, $start = 0, $count = 10, $order = '_id', $dir = 'DESC'): ResultSet
    {
        if ($order == 'id') {
            $order = '_id';
        }

        try {
            $query = $this->queryBuilder->getSearchParams($queryString, $type, $start, $count, $order, $dir);
            $this->logger->debug(sprintf('Elasticsearch Query %s', json_encode($query)));

            return $this->buildResultSet($this->client->search($query));
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

        return $this->indexer->buildIndex($documents);
    }

    public function remove($resourceId): bool
    {
        return $this->indexer->deleteDocument($resourceId);
    }

    public function supportCustomIndex(): bool
    {
        return true;
    }

    public function createIndexes(): void
    {
        $indexFile = $this->getIndexFile();

        $indexes = [];

        if ($indexFile && is_readable($indexFile)) {
            $indexes = require $indexFile;
        }

        foreach ($indexes as $index) {
            $index['index'] = $this->prefixer->prefix($index['index']);

            $this->client->indices()->create($index);
        }
    }

    public function flush(): array
    {
        return $this->client->indices()->delete(
            [
                'index' => implode(
                    ',',
                    $this->prefixer->prefixAll(IndexerInterface::AVAILABLE_INDEXES)
                ),
                'client' => [
                    'ignore' => 404
                ]
            ]
        );
    }

    public function ping(): bool
    {
        return $this->client->ping();
    }

    private function buildResultSet(array $elasticResult = []): ResultSet
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

        return new ResultSet($uris, $total);
    }

    public function __toPhpCode()
    {
        return __CLASS__;
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
            'config' .
            DIRECTORY_SEPARATOR .
            'index.conf.php';
    }
}
