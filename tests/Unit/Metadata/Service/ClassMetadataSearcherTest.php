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

namespace oat\taoAdvancedSearch\tests\Unit\model\Metadata\Service;

use core_kernel_classes_Property;
use oat\generis\model\data\Ontology;
use oat\generis\test\TestCase;
use oat\oatbox\log\LoggerService;
use oat\tao\elasticsearch\ElasticSearch;
use oat\tao\elasticsearch\SearchResult;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\Lists\Business\Domain\ClassCollection;
use oat\tao\model\Lists\Business\Domain\ClassMetadataSearchRequest;
use oat\tao\model\Lists\Business\Input\ClassMetadataSearchInput;
use oat\tao\model\Lists\Business\Service\ClassMetadataService;
use oat\tao\model\search\SearchProxy;
use oat\taoAdvancedSearch\model\Metadata\Service\ClassMetadataSearcher;
use PHPUnit\Framework\MockObject\MockObject;

class ClassMetadataSearcherTest extends TestCase
{
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

        $this->subject = new ClassMetadataSearcher();
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    ClassMetadataService::SERVICE_ID => $this->classMetadataService,
                    AdvancedSearchChecker::class => $this->advancedSearchChecker,
                    SearchProxy::SERVICE_ID => $this->search,
                    LoggerService::SERVICE_ID => $this->createMock(LoggerService::class),
                    Ontology::SERVICE_ID => $this->ontology
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
            ->willReturn(new SearchResult([], 0));

        $result = $this->subject->findAll(
            new ClassMetadataSearchInput(
                (new ClassMetadataSearchRequest())->setClassUri('class1')
            )
        );

        $rawResult = json_decode(json_encode($result->jsonSerialize()), true);

        $this->assertSame(null, $rawResult[0]['parent-class']);
        $this->assertSame('class1', $rawResult[0]['class']);
        $this->assertSame([], $rawResult[0]['metadata']);
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
                    'propertyLabel' => 'propertyLabel1',
                    'propertyType' => 'list',
                    'propertyAlias' => null,
                    'propertyValues' => [],
                ],
                [
                    'propertyUri' => 'propertyUri2',
                    'propertyLabel' => 'propertyLabel2',
                    'propertyType' => 'text',
                    'propertyAlias' => null,
                    'propertyValues' => [],
                ]
            ],
        ];
    }
}
