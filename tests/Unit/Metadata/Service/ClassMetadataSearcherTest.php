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
 * Copyright (c) 2021-2022 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\model\Metadata\Service;

use core_kernel_classes_Property;
use oat\generis\model\data\Ontology;
use oat\generis\test\ServiceManagerMockTrait;
use oat\oatbox\log\LoggerService;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\featureFlag\FeatureFlagChecker;
use oat\tao\model\featureFlag\FeatureFlagCheckerInterface;
use oat\tao\model\featureFlag\Service\FeatureFlagPropertiesMapping;
use oat\tao\model\Lists\Business\Domain\ClassCollection;
use oat\tao\model\Lists\Business\Domain\ClassMetadataSearchRequest;
use oat\tao\model\Lists\Business\Domain\MetadataCollection;
use oat\tao\model\Lists\Business\Input\ClassMetadataSearchInput;
use oat\tao\model\Lists\Business\Service\ClassMetadataService;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\Metadata\Service\ClassMetadataSearcher;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\ElasticSearch;
use oat\taoAdvancedSearch\model\SearchEngine\Query;
use oat\taoAdvancedSearch\model\SearchEngine\SearchResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Traversable;

class ClassMetadataSearcherTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var ClassMetadataSearcher */
    private $subject;

    /** @var ClassMetadataService|MockObject */
    private $classMetadataService;

    /** @var AdvancedSearchChecker|MockObject */
    private $advancedSearchChecker;

    /** @var ElasticSearch|MockObject */
    private $elasticSearch;

    /** @var SearchProxy|MockObject */
    private $search;

    /** @var Ontology|MockObject */
    private $ontology;

    public function setUp(): void
    {
        $this->classMetadataService = $this->createMock(ClassMetadataService::class);
        $this->advancedSearchChecker = $this->createMock(AdvancedSearchChecker::class);
        $this->elasticSearch = $this->createMock(ElasticSearch::class);
        $this->ontology = $this->createMock(Ontology::class);
        $this->search = $this->createMock(SearchProxy::class);
        $this->search
            ->method('getAdvancedSearch')
            ->willReturn($this->elasticSearch);

        $featureFlagPropertiesMapping = $this->createMock(FeatureFlagPropertiesMapping::class);
        $featureFlagPropertiesMapping
            ->method('getAllProperties')
            ->willReturn([]);

        $this->subject = new ClassMetadataSearcher();
        $this->subject->setServiceManager(
            $this->getServiceManagerMock(
                [
                    ClassMetadataService::SERVICE_ID => $this->classMetadataService,
                    AdvancedSearchChecker::class => $this->advancedSearchChecker,
                    SearchProxy::SERVICE_ID => $this->search,
                    LoggerService::SERVICE_ID => $this->createMock(LoggerService::class),
                    Ontology::SERVICE_ID => $this->ontology,
                    FeatureFlagChecker::class => $this->createMock(FeatureFlagCheckerInterface::class),
                    FeatureFlagPropertiesMapping::class => $featureFlagPropertiesMapping,
                ]
            )
        );
    }

    public function testFindAllWhenAdvancedSearchIsDisabledWillFallbackToGeneris(): void
    {
        $expectedCollection = new ClassCollection(...[]);

        $this->advancedSearchChecker
            ->method('isEnabled')
            ->willReturn(false);

        $this->classMetadataService
            ->method('findAll')
            ->willReturn($expectedCollection);

        $result = $this->subject->findAll(
            new ClassMetadataSearchInput(
                (new ClassMetadataSearchRequest())->setClassUri('classUri')
            )
        );

        $this->assertSame($expectedCollection, $result);
    }

    public function testFindAllUsingElasticSearch(): void
    {
        $property = $this->createMock(core_kernel_classes_Property::class);
        $property->method('getRelatedClass')
            ->willReturn(null);

        $property->method('getWidget')
            ->willReturn(null);

        $class = $this->createMock(core_kernel_classes_Property::class);
        $class->method('getLabel')
            ->willReturn('Class label');

        $this->ontology
            ->method('getProperty')
            ->willReturn($property);

        $this->ontology
            ->method('getClass')
            ->willReturn($class);

        $this->advancedSearchChecker
            ->method('isEnabled')
            ->willReturn(true);

        $this->elasticSearch
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                ...[
                    // Parent classes
                    new SearchResult(
                        [
                            $this->getMockResult('class1', 'parentClass1', ['class1', 'parentClass1']),
                        ],
                        1
                    ),
                    new SearchResult(
                        [],
                        0
                    ),
                    new SearchResult(
                        [],
                        0
                    ),
                    new SearchResult(
                        [],
                        0
                    )
                ]
            );

        $result = $this->subject->findAll(
            new ClassMetadataSearchInput(
                (new ClassMetadataSearchRequest())->setClassUri('class1')
            )
        );

        $rawResult = json_decode(json_encode($result->jsonSerialize()), true);

        $this->assertSame(null, $rawResult[0]['parent-class']);
        $this->assertSame('class1', $rawResult[0]['class']);
    }

    public function testFindAllUsingElasticSearchWithEmptyClassProperties(): void
    {
        $property = $this->createMock(core_kernel_classes_Property::class);
        $property->method('getRelatedClass')
            ->willReturn(null);

        $property->method('getWidget')
            ->willReturn(null);

        $class = $this->createMock(core_kernel_classes_Property::class);
        $class->method('getLabel')
            ->willReturn('Class label');

        $this->ontology
            ->method('getProperty')
            ->willReturn($property);

        $this->ontology
            ->method('getClass')
            ->willReturn($class);

        $this->advancedSearchChecker
            ->method('isEnabled')
            ->willReturn(true);

        $this->elasticSearch
            ->method('search')
            ->with($this->callback(function (Query $query) {
                return (($query->getQueryString() == '_id:"class1"')
                    && ($query->getIndex() == 'property-list'));
            }))
            ->willReturn(new SearchResult([], 0));

        $result = $this->subject->findAll(
            new ClassMetadataSearchInput(
                (new ClassMetadataSearchRequest())->setClassUri('class1')
            )
        );

        $generator = $result->getIterator();
        $this->assertInstanceOf(Traversable::class, $generator);

        $items = iterator_to_array($generator);
        $this->assertCount(1, $items);
        $this->assertNull($items[0]->getParentClass());
        $this->assertEquals('class1', $items[0]->getClass());

        $metadata = $items[0]->getMetaData();
        $this->assertInstanceOf(MetadataCollection::class, $metadata);
        $this->assertEquals(0, $metadata->count());
        $this->assertEmpty(iterator_to_array($metadata));
    }

    private function getMockResult(string $classId, ?string $parentClassUri, array $classPath): array
    {
        return [
            'id' => $classId,
            'parentClass' => $parentClassUri,
            'classPath' => $classPath,
            'propertiesTree' => [
                [
                    'propertyUri' => 'propertyUri1',
                    'propertyReference' => 'SearchBox_',
                    'propertyLabel' => 'propertyLabel1',
                    'propertyType' => 'list',
                    'propertyAlias' => null,
                    'propertyValues' => [],
                ],
                [
                    'propertyUri' => 'propertyUri2',
                    'propertyReference' => 'SearchBox_',
                    'propertyLabel' => 'propertyLabel2',
                    'propertyType' => 'text',
                    'propertyAlias' => null,
                    'propertyValues' => [],
                ]
            ],
        ];
    }
}
