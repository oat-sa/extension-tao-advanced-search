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

namespace oat\taoAdvancedSearch\model\Metadata\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use oat\generis\model\OntologyRdfs;
use oat\generis\persistence\PersistenceManager;
use common_persistence_SqlPersistence;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceIndexer;
use PDO;

class ListMetadataSynchonizer
{
    /** @var PersistenceManager */
    private $persistenceManager;

    /** @var string */
    private $persistenceId;

    /** @var ResourceIndexer */
    private $resourceIndexer;

    public function __construct(ResourceIndexer $resourceIndexer, PersistenceManager $persistenceManager, string $persistenceId)
    {
        $this->persistenceManager = $persistenceManager;
        $this->persistenceId = $persistenceId;
        $this->resourceIndexer = $resourceIndexer;
    }

    public function sync(string $listUri): void
    {
        $platform = $this->getPersistence()->getPlatForm();

        $propertyUris = $this->getProperties($platform->getQueryBuilder(), $listUri);
        $recordUris = $this->getRecordsUsingProperty($platform->getQueryBuilder(), $propertyUris);

        foreach ($recordUris as $record) {
            $uri = $record['subject'];
            $listItemValue = $record['object'];

            $this->resourceIndexer->addIndex($uri);
        }

        var_export($recordUris); exit(); //FIXME @TODO remove after testing
    }

    private function getProperties(QueryBuilder $queryBuilder, string $listUri): array
    {
        $expressionBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select('subject', 'predicate')
            ->from('statements')
            ->andWhere($expressionBuilder->eq('statements.predicate', ':predicate'))
            ->andWhere($expressionBuilder->eq('statements.object', ':list_uri'))
            ->setParameters(
                [
                    'predicate' => OntologyRdfs::RDFS_RANGE,
                    'list_uri' => $listUri,
                ]
            );

        $result = $queryBuilder->execute();

        return $result->getIterator()->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getRecordsUsingProperty(QueryBuilder $queryBuilder, array $propertyUris): array
    {
        if (empty($propertyUris)) {
            return [];
        }

        $expressionBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select('subject, predicate, object')
            ->from('statements')
            ->andWhere($expressionBuilder->in('statements.predicate', ':predicate'))
            ->setParameter('predicate', $propertyUris, Connection::PARAM_STR_ARRAY);

        $result = $queryBuilder->execute();

        return $result->getIterator()->fetchAll();
    }

    private function getPersistence(): common_persistence_SqlPersistence
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->persistenceManager->getPersistenceById($this->persistenceId);
    }
}
