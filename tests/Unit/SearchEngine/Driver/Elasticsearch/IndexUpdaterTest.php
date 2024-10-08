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

namespace oat\taoAdvancedSearch\tests\Unit\SearchEngine\Driver\Elasticsearch;

use DG\BypassFinals;
use Elastic\Elasticsearch\Client;
use PHPUnit\Framework\TestCase;
use oat\generis\test\ServiceManagerMockTrait;
use oat\oatbox\log\LoggerService;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\SearchEngine\Driver\Elasticsearch\IndexUpdater;
use oat\taoAdvancedSearch\model\SearchEngine\Exception\FailToRemovePropertyException;
use oat\taoAdvancedSearch\model\SearchEngine\Exception\FailToUpdatePropertiesException;
use oat\taoAdvancedSearch\model\SearchEngine\Service\IndexPrefixer;
use PHPUnit\Framework\MockObject\MockObject;

class IndexUpdaterTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var IndexUpdater */
    private $sut;

    /** @var Client|MockObject */
    private $client;

    /** @var IndexPrefixer|MockObject */
    private $prefixer;

    protected function setUp(): void
    {
        BypassFinals::enable();
        $this->sut = new IndexUpdater();
        $this->client = $this->createMock(Client::class);
        $this->prefixer = $this->createMock(IndexPrefixer::class);

        $this->sut->setServiceManager(
            $this->getServiceManagerMock(
                [
                    LoggerService::SERVICE_ID => $this->createMock(LoggerService::class),
                    IndexPrefixer::class => $this->prefixer,
                    Client::class => $this->client,
                ]
            )
        );

        $this->prefixer
            ->expects($this->any())
            ->method('prefix')
            ->willReturnArgument(0);
    }

    /**
     * @dataProvider provideValidPropertiesForUpdate
     */
    public function testUpdatePropertiesSuccessfully(array $properties, string $source): void
    {
        $this->client->expects($this->once())
            ->method('updateByQuery')
            ->with(
                [
                    'index' => 'items',
                    'type' => '_doc',
                    'conflicts' => 'proceed',
                    'wait_for_completion' => true,
                    'body' => [
                        'query' => [
                            'multi_match' => [
                                'query' => TaoOntology::CLASS_URI_ITEM,
                                'fields' => ['type', '_id'],
                            ],
                        ],
                        'script' => [
                            'source' => $source
                        ]
                    ]
                ]
            );

        $this->sut->updatePropertiesName($properties);
    }

    public function testExceptionWhenUpdatingProperties(): void
    {
        $this->expectException(FailToUpdatePropertiesException::class);

        $this->client->expects($this->once())
            ->method('updateByQuery')
            ->willThrowException(new \BadMethodCallException());

        $this->sut->updatePropertiesName(
            [
                [
                    'newName' => 'test',
                    'oldName' => 'devel',
                    'parentClasses' => [],
                    'type' => TaoOntology::CLASS_URI_ITEM
                ]
            ]
        );
    }

    /**
     * @dataProvider provideInvalidPropertiesForUpdate
     */
    public function testDoNotUpdateProperties(array $properties): void
    {
        $this->client->expects($this->never())
            ->method('updateByQuery');

        $this->sut->updatePropertiesName($properties);
    }

    /**
     * @dataProvider provideValidPropertiesForRemoval
     */
    public function testRemovePropertySuccessfully(array $property, string $source): void
    {
        $this->client->expects($this->once())
            ->method('updateByQuery')
            ->with(
                [
                    'index' => 'items',
                    'type' => '_doc',
                    'conflicts' => 'proceed',
                    'wait_for_completion' => true,
                    'body' => [
                        'query' => [
                            'multi_match' => [
                                'query' => TaoOntology::CLASS_URI_ITEM,
                                'fields' => ['type', '_id'],
                            ],
                        ],
                        'script' => [
                            'source' => $source
                        ]
                    ]
                ]
            );

        $this->sut->deleteProperty($property);
    }

    public function testExceptionWhenRemovingProperty(): void
    {
        $this->expectException(FailToRemovePropertyException::class);

        $this->client->expects($this->once())
            ->method('updateByQuery')
            ->willThrowException(new \BadMethodCallException());

        $this->sut->deleteProperty(
            [
                'name' => 'test',
                'parentClasses' => ['someClass'],
                'type' => TaoOntology::CLASS_URI_ITEM
            ]
        );
    }

    /**
     * @dataProvider provideInvalidPropertiesForRemoval
     */
    public function testDoNotRemoveProperty(array $property): void
    {
        $this->client->expects($this->never())
            ->method('updateByQuery');

        $this->sut->deleteProperty($property);
    }

    public function testUpdatePropertyValueSuccessfuly()
    {
        $documentUri = 'https://tao.docker.localhost/ontologies/tao.rdf#i5ef45f413088c8e7901a84708e84ec';
        $validType = 'http://www.tao.lu/Ontologies/TAOItem.rdf#Item';
        $source = 'ctx._source[\'RadioBox_isTest\'] = [\'123456\'];';

        $this->client->expects($this->once())
            ->method('updateByQuery')
            ->with(
                [
                    'index' => 'items',
                    'type' => '_doc',
                    'conflicts' => 'proceed',
                    'wait_for_completion' => true,
                    'body' => [
                        'query' => [
                            'multi_match' => [
                                'query' => $documentUri,
                                'fields' => [
                                    'type',
                                    '_id'
                                ],
                            ],
                        ],
                        'script' => [
                            'source' => $source
                        ]
                    ]
                ]
            );

        $this->sut->updatePropertyValue(
            $documentUri,
            [
                $validType
            ],
            'RadioBox_isTest',
            [
                '123456'
            ]
        );
    }

    public function testUpdatePropertyValueDoNothingInCaseUnclassifiedIndex()
    {
        $this->client->expects($this->never())
            ->method('updateByQuery');

        $invalidType = 'https://tao.docker.localhost/ontologies/tao.rdf#Invalid';

        $this->sut->updatePropertyValue($invalidType, [], 'RadioBox_isTest', ['123456']);
    }

    public function testExceptionWhenUpdatingPropertyValue()
    {
        $this->expectException(FailToUpdatePropertiesException::class);

        $this->client->expects($this->once())
            ->method('updateByQuery')
            ->willThrowException(new \BadMethodCallException());
        $documentUri = 'https://tao.docker.localhost/ontologies/tao.rdf#i5ef45f413088c8e7901a84708e84ec';
        $validType = 'http://www.tao.lu/Ontologies/TAOItem.rdf#Item';

        $this->sut->updatePropertyValue(
            $documentUri,
            [
                $validType
            ],
            'RadioBox_isTest',
            ['123456']
        );
    }

    public function provideValidPropertiesForUpdate(): array
    {
        return [
            'Single' => [
                'properties' =>
                    [
                        [
                            'newName' => 'test',
                            'oldName' => 'devel',
                            'parentClasses' => [],
                            'type' => TaoOntology::CLASS_URI_ITEM
                        ]
                    ],
                'source' => 'ctx._source[\'test\'] = ctx._source[\'devel\']; ctx._source.remove(\'devel\');'
            ],
            'Multiple' => [
                'properties' =>
                    [
                        [
                            'newName' => 'test',
                            'oldName' => 'devel',
                            'parentClasses' => [],
                            'type' => TaoOntology::CLASS_URI_ITEM
                        ],
                        [
                            'newName' => 'CheckBox_property-6',
                            'oldName' => 'TextArea_devel',
                            'parentClasses' => [],
                            'type' => TaoOntology::CLASS_URI_ITEM
                        ],
                    ],
                'source' => "ctx._source['test'] = ctx._source['devel']; " .
                    "ctx._source['CheckBox_property-6'] = ctx._source['TextArea_devel']; " .
                    "ctx._source.remove('devel'); " .
                    "ctx._source.remove('TextArea_devel');"
            ],
        ];
    }

    public function provideInvalidPropertiesForUpdate(): array
    {
        return [
            'With No OldName' => [
                'properties' =>
                    [
                        [
                            'newName' => 'test',
                            'parentClasses' => [],
                            'type' => TaoOntology::CLASS_URI_ITEM
                        ]
                    ]
            ],
            'With No NewName' => [
                'properties' =>
                    [
                        [
                            'oldName' => 'devel',
                            'parentClasses' => [],
                            'type' => TaoOntology::CLASS_URI_ITEM
                        ]
                    ]
            ],
            'With No Type' => [
                'properties' =>
                    [
                        [
                            'newName' => 'test',
                            'oldName' => 'devel',
                            'parentClasses' => [],
                        ]
                    ]
            ],
            'With No parentClass' => [
                'properties' =>
                    [
                        [
                            'newName' => 'test',
                            'oldName' => 'devel',
                            'type' => TaoOntology::CLASS_URI_ITEM
                        ]
                    ]
            ],
            'With invalidType' => [
                'properties' =>
                    [
                        [
                            'newName' => 'test',
                            'oldName' => 'devel',
                            'type' => 'invalidType',
                            'parentClasses' => [],
                        ]
                    ]
            ],
        ];
    }

    public function provideValidPropertiesForRemoval(): array
    {
        return [
            [
                'property' => [
                    'name' => 'prop1',
                    'parentClasses' => [],
                    'type' => TaoOntology::CLASS_URI_ITEM
                ],
                'source' => 'ctx._source.remove(\'prop1\');'
            ],
            [
                'property' => [
                    'name' => 'prop2',
                    'parentClasses' => [],
                    'type' => TaoOntology::CLASS_URI_ITEM
                ],
                'source' => 'ctx._source.remove(\'prop2\');'
            ],
        ];
    }

    public function provideInvalidPropertiesForRemoval(): array
    {
        return [
            'with no name' => [
                'property' => [
                    'name' => '',
                    'parentClasses' => [],
                    'type' => TaoOntology::CLASS_URI_ITEM
                ]
            ],
            'with no type' => [
                'property' => [
                    'name' => 'prop',
                    'parentClasses' => [],
                    'type' => ''
                ]
            ],
            'with invalid type' => [
                'property' => [
                    'name' => 'prop',
                    'parentClasses' => [],
                    'type' => 'invalid'
                ]
            ]
        ];
    }
}
