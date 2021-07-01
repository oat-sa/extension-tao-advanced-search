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

            $this->addProcessedClass(
                $currentClassUri,
                null, //@TODO Check if this is required
                $this->getRelatedProperties($currentClassUri)
            );

            return new ClassCollection(...array_values($this->processedClasses));
        }

        return $this->getClassMetadataSearcher()->findAll($input);
    }

    /**
     * Given the following class tree relation:
     *
     * a []
     * b [a]
     *   b1 [a,b]
     *   b2 [a,b]
     * c [a]
     *   c1 [a,c]
     *      c1.1 [a,c,c1]
     *      c1.2 [a,c,c1]
     *   c2 [a,c]
     *      c2.1 [a,c,c2]
     *
     * - If I select class c1, then I get properties from: c1.1, c1.2, c1, c, a
     * - If I select class b2, then I get properties from: b2, b, a
     * - If I select class a, then I get properties from: a, b, b1, b2, c, c1, c1.1, c1.2, c2, c2.1
     */
    private function getRelatedProperties(string $classUri): array
    {
        $allProperties = [];

        // 1 - Get data for class by id
        $result = $this->executeQuery('_id', $classUri);
        $result = current($result);

        $allProperties[] = $result;

        // 2 - Get properties for all in the classPath (parent classes);
        foreach ($result['classPath'] as $parentClassId) {
            $result = $this->executeQuery('_id', $parentClassId);

            foreach ($result as $resultUnit) {
                $allProperties[] = $resultUnit;
            }
        }

        // 3 - Get properties fro children
        $result = $this->executeQuery('classPath', $classUri);

        foreach ($result as $resultUnit) {
            $allProperties[] = $resultUnit;
        }

        // 4 - Filter out possible duplicated properties:
        $filteredProperties = [];
        $processedProperties = [];

        foreach ($allProperties as $propertyGroup) {
            foreach ($propertyGroup['propertiesTree'] as $property) {
                if (in_array($property['propertyUri'], $processedProperties)) {
                    continue;
                }

                $processedProperties[] = $property['propertyUri'];
                $filteredProperties[] = $property;
            }
        }

        return $filteredProperties;
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
        $query = (new Query('property-list'))
            ->addCondition(sprintf('%s:"%s"', $field, $value));

        return $this->getSearcher()->search($query);
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
}
