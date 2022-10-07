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
 *
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\model\Metadata\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use oat\generis\model\OntologyRdfs;
use oat\tao\model\Lists\Business\Event\ListSavedEvent;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexer;
use PDO;

class ListSavedEventListener
{
    private const PROCESS_CHUCK_SIZE = 100;

    /** @var ResourceIndexer */
    private $resourceIndexer;

    /** @var QueryBuilder */
    private $queryBuilder;

    /** @var string */
    private $chunkSize = self::PROCESS_CHUCK_SIZE;

    public function __construct(ResourceIndexer $resourceIndexer, QueryBuilder $queryBuilder)
    {
        $this->resourceIndexer = $resourceIndexer;
        $this->queryBuilder = $queryBuilder;
    }

    public function setChunkSize(int $chunkSize): self
    {
        $this->chunkSize = $chunkSize;

        return $this;
    }

    public function listen(ListSavedEvent $event): void
    {
        $propertyUris = $this->getProperties($event->getListUri());
        $allRecordUris = $this->getRecordsUsingProperty($propertyUris);

        foreach (array_chunk($allRecordUris, $this->chunkSize) as $recordUrisChunk) {
            $this->resourceIndexer->addIndex($recordUrisChunk);
        }
    }

    private function getProperties(string $listUri): array
    {
        $this->queryBuilder->resetQueryParts();

        $expressionBuilder = $this->queryBuilder->expr();

        $this->queryBuilder
            ->select('subject')
            ->from('statements')
            ->andWhere($expressionBuilder->eq('statements.predicate', ':predicate'))
            ->andWhere($expressionBuilder->eq('statements.object', ':list_uri'))
            ->setParameters(
                [
                    'predicate' => OntologyRdfs::RDFS_RANGE,
                    'list_uri' => $listUri,
                ]
            );

        return $this->queryBuilder->execute()->getIterator()->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getRecordsUsingProperty(array $propertyUris): array
    {
        if (empty($propertyUris)) {
            return [];
        }

        $this->queryBuilder->resetQueryParts();

        $expressionBuilder = $this->queryBuilder->expr();

        $this->queryBuilder
            ->select('subject')
            ->from('statements')
            ->andWhere($expressionBuilder->in('statements.predicate', ':predicate'))
            ->setParameter('predicate', $propertyUris, Connection::PARAM_STR_ARRAY);

        return $this->queryBuilder->execute()->getIterator()->fetchAll(PDO::FETCH_COLUMN);
    }
}
