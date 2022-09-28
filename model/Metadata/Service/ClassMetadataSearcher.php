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
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Query;

class ClassMetadataSearcher extends ConfigurableService implements ClassMetadataSearcherInterface
{
    private const BASE_LIST_ITEMS_URI = '/tao/PropertyValues/get?propertyUri=%s';

    use OntologyAwareTrait;

    public function findAll(ClassMetadataSearchInput $input): ClassCollection
    {
        if ($this->getAdvancedSearchChecker()->isEnabled()) {
            $currentClassUri = $input->getSearchRequest()->getClassUri();

            return $this->createClassCollection($currentClassUri, $this->getProperties($currentClassUri));
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
    private function getProperties(string $classUri): array
    {
        $result = $this->getMainClassProperties($classUri);

        if (empty($result)) {
            return [];
        }

        $allProperties = [$result];
        $allProperties = $this->getRelatedProperties($result, $allProperties);
        $allProperties = $this->getClassPathProperties($classUri, $allProperties);

        return $this->filterDuplicatedProperties($allProperties);
    }

    private function getClassPathProperties(string $classUri, array $allProperties): array
    {
        foreach ($this->executeQuery('classPath', $classUri) as $resultUnit) {
            $allProperties[] = $resultUnit;
        }

        return $allProperties;
    }

    private function getRelatedProperties(array $result, array $allProperties): array
    {
        foreach (($result['classPath'] ?? []) as $parentClassId) {
            $result = $this->executeQuery('_id', $parentClassId);

            if ($result->getTotalCount() === 0) {
                continue;
            }

            foreach ($result as $resultUnit) {
                $allProperties[] = $resultUnit;
            }
        }

        return $allProperties;
    }

    private function getMainClassProperties(string $classUri): array
    {
        $results = (array) $this->executeQuery('_id', $classUri);

        return !empty($results) ? current($results) : [];
    }

    private function filterDuplicatedProperties(array $allProperties): array
    {
        $properties = [];

        foreach ($allProperties as $propertyGroup) {
            foreach (($propertyGroup['propertiesTree'] ?? []) as $property) {
                if (empty($property['propertyUri']) || array_key_exists($property['propertyUri'], $properties)) {
                    continue;
                }

                $properties[$property['propertyUri']] = $property;
            }
        }

        return $properties;
    }

    private function createClassCollection(string $classUri, array $properties): ClassCollection
    {
        $metadataCollection = new MetadataCollection();

        $duplicatedUris = $this->getDuplicatedPropertyUris($properties);

        foreach ($properties as $property) {
            $relatedClass = $this->getProperty($property['propertyUri'])->getRelatedClass();

            $metadata = (new Metadata())
                ->setSortId($property['propertyRawReference'] ?? $property['propertyUri'])
                ->setIsSortable($this->isPropertySortable($property))
                ->setLabel($property['propertyLabel'])
                ->setAlias($property['propertyAlias'])
                ->setClassLabel($relatedClass ? $relatedClass->getLabel() : null)
                ->setPropertyUri($property['propertyUri'])
                ->setType($property['propertyType'])
                ->setValues($property['propertyValues'])
                ->setUri(
                    $this->getPropertyListUri(
                        $property['propertyUri'],
                        $property['propertyType'],
                        $property['propertyValues']
                    )
                );

            if (in_array($property['propertyUri'], $duplicatedUris, true)) {
                $metadata->markAsDuplicated();
            }

            $metadataCollection->addMetadata($metadata);
        }

        return new ClassCollection(
            (new ClassMetadata())
                ->setClass($classUri)
                ->setLabel($this->getClass($classUri)->getLabel())
                ->setMetaData($metadataCollection)
        );
    }

    private function isPropertySortable(array $property): bool
    {
        return strpos($property['propertyReference'], 'RadioBox') === 0 ||
            strpos($property['propertyReference'], 'ComboBox') === 0 ||
            strpos($property['propertyReference'], 'Checkbox') === 0 ||
            strpos($property['propertyReference'], 'TextBox') === 0 ||
            strpos($property['propertyReference'], 'TextArea') === 0 ||
            strpos($property['propertyReference'], 'HTMLArea') === 0;
    }

    private function getDuplicatedPropertyUris(array $properties): array
    {
        $duplicatedGroups = [];

        foreach ($properties as $property) {
            $unifiedLabel = trim(strtolower($property['propertyLabel']));

            if (!array_key_exists($unifiedLabel, $duplicatedGroups)) {
                $duplicatedGroups[$unifiedLabel] = [];
            }

            $duplicatedGroups[$unifiedLabel][] = $property['propertyUri'];
        }

        $duplicated = [];

        foreach ($duplicatedGroups as $group) {
            if (count($group) > 1) {
                $duplicated = array_merge($duplicated, $group);
            }
        }

        return $duplicated;
    }

    private function executeQuery(string $field, string $value): ResultSet
    {
        $query = (new Query('property-list'))
            ->addCondition(sprintf('%s:"%s"', $field, $value));

        $result = $this->getSearcher()->search($query);

        if ($result->getTotalCount() === 0) {
            $this->logWarning(sprintf('There is no metadata saved for %s:%s at %s', $field, $value, __METHOD__));
        }

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
}
