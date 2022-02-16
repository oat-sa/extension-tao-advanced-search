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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Search;

use ArrayIterator;
use Exception;
use Iterator;
use oat\oatbox\service\ConfigurableService;
use oat\tao\model\search\ResultSet;
use oat\tao\model\search\SyntaxException;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;

class OpenSearch extends ConfigurableService implements SearchInterface
{
    /** @var Client */
    private $client;

    /** @var QueryBuilder */
    private $queryBuilder;

    public function countDocuments(string $index): int
    {
        $result = $this->getClient()->count(
            [
                'index' => $index
            ]
        );

        return $result['count'] ?? 0;
    }

    public function search(Query $query): ResultSet
    {
        $query = [
            'index' => $query->getIndex(),
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

        $results = $this->buildResultSet(
            $this->getClient()->search($query)
        );

        return new ResultSet(
            $results->getArrayCopy(),
            $results->getTotalCount()
        );
    }

    /**
     * @inheritDoc
     */
    public function query($queryString, $type, $start = 0, $count = 10, $order = '_id', $dir = 'DESC'): ResultSet
    {
        if ($order == 'id') {
            $order = '_id';
        }

        try {
            $query = $this->getQueryBuilder()->getSearchParams($queryString, $type, $start, $count, $order, $dir);
            $this->getLogger()->debug(sprintf('OpenSearch Query %s', json_encode($query)));

            return $this->buildResultSet(
                $this->getClient()->search(
                    $query
                )
            );
        } catch (Exception $exception) {
            switch ($exception->getCode()) {
                case 400:
                    $json = json_decode($exception->getMessage(), true);
                    $message = __(
                        'There is an error in your search query, system returned: %s',
                        $json['error']['reason'] ?? ''
                    );
                    $this->getLogger()->error(sprintf('OpenSearch: %s %s', $message, $exception->getMessage()));
                    throw new SyntaxException($queryString, $message);
                default:
                    $message = 'An unknown error occurred during search';
                    $this->getLogger()->error(sprintf('OpenSearch: %s "%s"', $message, $exception->getMessage()));
                    throw new SyntaxException($queryString, __($message));
            }
        }
    }

    public function index($documents = []): int
    {
        $documents = $documents instanceof Iterator ? $documents : new ArrayIterator($documents);

        return $this->getIndexer()->buildIndex($documents);
    }

    public function remove($resourceId): bool
    {
        return $this->getIndexer()->deleteDocument($resourceId);
    }

    public function supportCustomIndex(): bool
    {
        return true;
    }

    public function createIndexes(): void
    {
        $indexFiles = $this->getOption('indexFiles', '');
        $indexes = [];

        if ($indexFiles && is_readable($indexFiles)) {
            $indexes = require $indexFiles;
        }

        foreach ($indexes as $index) {
            $this->getClient()->indices()->create($index);
        }
    }

    public function flush(): array
    {
        return $this->getClient()->indices()->delete(
            [
                'index' => implode(',', IndexerInterface::AVAILABLE_INDEXES),
                'client' => [
                    'ignore' => 404
                ]
            ]
        );
    }

    public function getClient(): Client
    {
        if (is_null($this->client)) {
            $this->client = ClientBuilder::create()
                ->setHosts($this->getOption('hosts'))
                ->build();
        }

        return $this->client;
    }


    private function getQueryBuilder(): QueryBuilder
    {
        if (is_null($this->queryBuilder)) {
            $this->queryBuilder = $this->getServiceLocator()->get(QueryBuilder::class);
        }

        return $this->queryBuilder;
    }

    private function getIndexer(): OpenSearchIndexer
    {
        return new OpenSearchIndexer($this->getClient(), $this->getLogger());
    }

    private function buildResultSet(array $result = []): ResultSet
    {
        $uris = [];
        $total = 0;

        if ($result && isset($result['hits'])) {
            foreach ($result['hits']['hits'] as $document) {
                $document['_source']['id'] = $document['_id'];
                $uris[] = $document['_source'];
            }

            $total = is_array($result['hits']['total'])
                ? $result['hits']['total']['value']
                : $result['hits']['total'];
        }

        return new ResultSet($uris, $total);
    }
}
