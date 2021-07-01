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

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\service\ConfigurableService;
use oat\tao\elasticsearch\ElasticSearch;
use oat\tao\elasticsearch\Query;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\Lists\Business\Contract\ClassMetadataSearcherInterface;
use oat\tao\model\Lists\Business\Domain\ClassCollection;
use oat\tao\model\Lists\Business\Domain\ClassMetadata;
use oat\tao\model\Lists\Business\Domain\Metadata;
use oat\tao\model\Lists\Business\Domain\MetadataCollection;
use oat\tao\model\Lists\Business\Input\ClassMetadataSearchInput;
use oat\tao\model\Lists\Business\Service\ClassMetadataService;
use oat\tao\model\Lists\Business\Service\GetClassMetadataValuesService;
use oat\tao\model\search\ResultSet;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\Metadata\Cache\ClassMetadataCache;

class ClassMetadataSearcher extends ConfigurableService implements ClassMetadataSearcherInterface
{
    use OntologyAwareTrait;

    public const CACHE = __CLASS__ . '::%s';
    private const BASE_LIST_ITEMS_URI = '/tao/PropertyValues/get?propertyUri=%s';

    /** @var array */
    private $processedClasses = [];

    public function findAll(ClassMetadataSearchInput $input): ClassCollection
    {
        if ($this->getAdvancedSearchChecker()->isEnabled()) {
            $currentClassUri = $input->getSearchRequest()->getClassUri();

            $cachedValue = $this->getCache()->retrieve($currentClassUri);

            $this->addProcessedClass(
                $currentClassUri,
                $cachedValue['parentClass'],
                $this->incrementProperties($cachedValue, [])
            );

//            $properties = [];
//
//            $this->getParentProperties($currentClassUri, $properties);
//            $this->getSubProperties($currentClassUri, $properties);

            return new ClassCollection(...array_values($this->processedClasses));
        }

        return $this->getClassMetadataSearcher()->findAll($input);
    }

    private function getSubProperties(string $classUri, array $properties = []): void
    {
        foreach ($this->getClass($classUri)->getSubClasses(true) as $subclass) {
            $result = $this->executeQuery('_id', $subclass->getUri());

            $this->processResult($result, $properties, $classUri);
        }
    }

    private function getParentProperties(string $classUri, array &$properties): void
    {
        $result = $this->executeQuery('_id', $classUri);
        $result = current($result);

        if ($result) {
            $properties = $this->incrementProperties($result, $properties);

            if (!empty($result['parentClass']) && !$this->wasClassProcessed($result['parentClass'])) {
                $this->getParentProperties($result['parentClass'], $properties);
            }
        }

        $this->addProcessedClass($classUri, $result['parentClass'] ?? null, $properties);
    }

    private function incrementProperties(array $res, array $properties): array
    {
        foreach ($res['propertiesTree'] as $prop) {
            if (empty($properties[$prop['propertyUri']])) {
                $properties[$prop['propertyUri']] = $prop;
            }
        }

        return $properties;
    }

    private function wasClassProcessed(string $classUri): bool
    {
        return in_array($classUri, array_keys($this->processedClasses), true);
    }

    private function addProcessedClass(string $classUri, ?string $parentClass, array $properties)
    {
        $metadataCollection = new MetadataCollection();

        foreach ($properties as $property) {
            $metadataCollection->addMetadata(
                (new Metadata())
                    ->setLabel($property['propertyLabel'])
                    ->setPropertyUri($property['propertyUri'])
                    ->setUri(
                        $this->getPropertyListUri(
                            $property['propertyUri'],
                            $property['propertyType'],
                            $property['propertyValues']
                        )
                    )
                    ->setType($property['propertyType'])
                    ->setValues($property['propertyValues'])
            );
        }

        $this->processedClasses[$classUri] = (new ClassMetadata())
            ->setClass($classUri)
            ->setParentClass($parentClass)
            ->setLabel($classUri)
            ->setMetaData($metadataCollection);
    }

    private function executeQuery(string $field, string $value): ResultSet
    {
        $cachedValue = $this->getCache()->retrieve($value);

        if ($cachedValue !== null) {
            return new ResultSet(
                [
                    array_merge(
                        [
                            'id' => $value,
                        ],
                        $cachedValue
                    )
                ],
                1
            );
        }

        return new ResultSet([], 0);
        //FIXME
        //FIXME
        //FIXME
        //FIXME

        $query = (new Query('property-list'))
            ->addCondition(sprintf('%s:"%s"', $field, $value));

        $result = $this->getSearcher()->search($query);

        return $result;
    }

    private function getPropertyListUri(string $propertyUri, string $type, ?array $values): ?string
    {
        if ($type !== GetClassMetadataValuesService::DATA_TYPE_LIST || $values) {
            return null;
        }

        return sprintf(self::BASE_LIST_ITEMS_URI, urlencode($propertyUri));
    }

    private function getClassMetadataSearcher(): ClassMetadataSearcherInterface
    {
        return $this->getServiceLocator()->get(ClassMetadataService::SERVICE_ID);
    }

    private function getAdvancedSearchChecker(): AdvancedSearchChecker
    {
        return $this->getServiceLocator()->get(AdvancedSearchChecker::class);
    }

    private function getSearcher(): ElasticSearch
    {
        return $this->getSearch()->getAdvancedSearch();
    }

    private function getSearch(): SearchProxy
    {
        return $this->getServiceLocator()->get(SearchProxy::SERVICE_ID);
    }

    private function processResult(ResultSet $result, array $properties, string $classUri): void
    {
        foreach ($result as $res) {
            $newProperties = $this->incrementProperties($res, $properties);

            if (!$this->wasClassProcessed($res['id'])) {
                $this->addProcessedClass($res['id'], $classUri, $newProperties);
                $this->getSubProperties($res['id'], $newProperties);
            }
        }
    }

    private function getCache(): ClassMetadataCache
    {
        return $this->getServiceLocator()->get(ClassMetadataCache::class);
    }
}
