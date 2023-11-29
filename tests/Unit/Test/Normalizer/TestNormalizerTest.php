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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoAdvancedSearch\tests\Unit\Test\Normalizer;

use core_kernel_classes_Resource;
use oat\generis\test\ServiceManagerMockTrait;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilder;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;
use oat\tao\model\search\index\IndexDocument;
use oat\taoAdvancedSearch\model\Test\Normalizer\TestNormalizer;
use PHPUnit\Framework\TestCase;
use taoQtiTest_models_classes_QtiTestService as QtiTestService;
use PHPUnit\Framework\MockObject\MockObject;

class TestNormalizerTest extends TestCase
{
    use ServiceManagerMockTrait;

    /** @var TestNormalizer */
    private $sut;

    /** @var QtiTestService|MockObject */
    private $qtiTestService;

    /** @var IndexDocumentBuilderInterface|MockObject */
    private $documentBuilder;

    /** @var IndexDocument|MockObject */
    private $document;

    public function setUp(): void
    {
        $this->document = $this->createMock(IndexDocument::class);
        $this->qtiTestService = $this->createMock(QtiTestService::class);
        $this->documentBuilder = $this->createMock(IndexDocumentBuilder::class);

        $this->sut = new TestNormalizer($this->qtiTestService, $this->documentBuilder);
    }

    /**
     * @dataProvider testDataProvider
     */
    public function testNormalize(string $testJson, array $expectedNormalizedBody): void
    {
        $item1 = $this->createMock(core_kernel_classes_Resource::class);
        $item2 = $this->createMock(core_kernel_classes_Resource::class);

        $item1->method('getUri')
            ->willReturn('item://1');

        $item2->method('getUri')
            ->willReturn('item://2');

        $test = $this->createMock(core_kernel_classes_Resource::class);

        $this->document
            ->method('getBody')
            ->willReturn(
                [
                    'type' => ['document type'],
                ]
            );

        $this->documentBuilder
            ->expects($this->once())
            ->method('createDocumentFromResource')
            ->with($test)
            ->willReturn($this->document);

        $this->qtiTestService
            ->expects($this->once())
            ->method('getItems')
            ->with($test)
            ->willReturn([$item1, $item2]);

        $this->qtiTestService
            ->expects($this->once())
            ->method('getJsonTest')
            ->with($test)
            ->willReturn($testJson);

        $this->assertEquals($expectedNormalizedBody, $this->sut->normalize($test)->getBody());
    }

    private function testDataProvider(): array
    {
        $qtiTestStructureJson = '{"qti-type":"assessmentTest","identifier":"testIdentifier","title":"title","toolVersion":"beta","testParts":[{"qti-type":"testPart","identifier":"cluster-id-stage1","navigationMode":0,"submissionMode":0,"preConditions":[],"branchRules":[],"itemSessionControl":{"maxAttempts":0,"qti-type":"itemSessionControl"},"assessmentSections":[{"qti-type":"assessmentSection","title":"Section","identifier":"assessmentSection-0","preConditions":[],"branchRules":[],"index":0,"sectionParts":[{"href":"https://itemUri1","label":"item-1","qti-type":"assessmentItemRef","categories":["cluster-id-stage1","x-tao-option-reviewScreen"],"identifier":"item1","timeLimits":{"qti-type":"timeLimits","maxTime":60}}],"timeLimits":{"qti-type":"timeLimits","maxTime":60}}]},{"qti-type":"testPart","identifier":"cluster-id-stage1-Unit11","navigationMode":0,"submissionMode":0,"preConditions":[],"branchRules":[],"assessmentSections":[{"qti-type":"assessmentSection","title":"Section","visible":true,"identifier":"assessmentSection-1","required":true,"preConditions":[],"branchRules":[],"index":0,"itemSessionControl":{"maxAttempts":0,"qti-type":"itemSessionControl"},"categories":[],"isSubsection":false,"sectionParts":[{"href":"https://itemUri0","label":"item-0","qti-type":"assessmentItemRef","categories":["cluster-id-stage1","x-tao-option-reviewScreen"],"identifier":"item-0","timeLimits":{"qti-type":"timeLimits","maxTime":60}}],"timeLimits":{"qti-type":"timeLimits","maxTime":60}}]}],"timeLimits":{"maxTime":3600,"qti-type":"timeLimits"}}'; // phpcs:ignore
        $qtiTestStructureToBeIndexed = [
            'qti-type' => 'assessmentTest',
            'identifier' => 'testIdentifier',
            'testParts' => [
                [
                    'qti-type' => 'testPart',
                    'identifier' => 'cluster-id-stage1',
                    'navigationMode' => 0,
                    'assessmentSections' => [
                        [
                            'qti-type' => 'assessmentSection',
                            'identifier' => 'assessmentSection-0',
                            'sectionParts' => [
                                [
                                    'href' => 'https://itemUri1',
                                    'qti-type' => 'assessmentItemRef',
                                    'categories' => ['cluster-id-stage1', 'x-tao-option-reviewScreen'],
                                    'identifier' => 'item1',
                                    'timeLimits' => [
                                        'qti-type' => 'timeLimits',
                                        'maxTime' => 60,
                                    ],
                                ],
                            ],
                            'timeLimits' => [
                                'qti-type' => 'timeLimits',
                                'maxTime' => 60,
                            ],
                        ],
                    ],
                ],
                [
                    'qti-type' => 'testPart',
                    'identifier' => 'cluster-id-stage1-Unit11',
                    'navigationMode' => 0,
                    'assessmentSections' => [
                        [
                            'qti-type' => 'assessmentSection',
                            'identifier' => 'assessmentSection-1',
                            'categories' => [],
                            'sectionParts' => [
                                [
                                    'href' => 'https://itemUri0',
                                    'qti-type' => 'assessmentItemRef',
                                    'categories' => ['cluster-id-stage1', 'x-tao-option-reviewScreen'],
                                    'identifier' => 'item-0',
                                    'timeLimits' => [
                                        'qti-type' => 'timeLimits',
                                        'maxTime' => 60,
                                    ],
                                ],
                            ],
                            'timeLimits' => [
                                'qti-type' => 'timeLimits',
                                'maxTime' => 60,
                            ],
                        ],
                    ],
                ],
            ],
            'timeLimits' => [
                'maxTime' => 3600,
                'qti-type' => 'timeLimits',
            ],
        ];

        return [
            'Simple' => [
                'testJson' => '{"identifier": "test_id"}',
                'expectedNormalizedBody' => [
                    'type' => ['document type'],
                    'item_uris' => [
                        'item://1',
                        'item://2'
                    ],
                    'qti_identifier' => 'test_id',
                    'test_qti_structure' => [
                        'identifier' => 'test_id'
                    ]
                ],
            ],
            'Has categories and timers' => [
                'testJson' => $qtiTestStructureJson,
                'expectedNormalizedBody' => [
                    'type' => ['document type'],
                    'item_uris' => [
                        'item://1',
                        'item://2'
                    ],
                    'qti_identifier' => 'testIdentifier',
                    'test_qti_structure' => $qtiTestStructureToBeIndexed
                ],
            ]
        ];
    }
}
