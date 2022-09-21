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

namespace oat\taoAdvancedSearch\tests\Unit\model\Metadata\Service;

use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\Lists\Business\Contract\ClassMetadataSearcherInterface;
use oat\tao\model\Lists\Business\Domain\ClassCollection;
use oat\tao\model\Lists\Business\Domain\ClassMetadata;
use oat\tao\model\Lists\Business\Domain\ClassMetadataSearchRequest;
use oat\tao\model\Lists\Business\Domain\Metadata;
use oat\tao\model\Lists\Business\Domain\MetadataCollection;
use oat\tao\model\search\Contract\SearchSettingsServiceInterface;
use oat\tao\model\search\SearchSettings;
use oat\taoAdvancedSearch\model\Metadata\Service\AdvancedSearchSettingsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdvancedSearchSettingsServiceTest extends TestCase
{
    /** @var AdvancedSearchSettingsService */
    private $subject;

    /** @var ClassMetadataSearcherInterface|MockObject */
    private $classMetadataSearcher;

    /** @var SearchSettingsServiceInterface|MockObject */
    private $defaultSearchSettingsService;

    /** @var AdvancedSearchChecker|MockObject */
    private $advancedSearchChecker;

    public function setUp(): void
    {
        $this->classMetadataSearcher = $this->createMock(ClassMetadataSearcherInterface::class);
        $this->defaultSearchSettingsService =  $this->createMock(SearchSettingsServiceInterface::class);
        $this->advancedSearchChecker = $this->createMock(AdvancedSearchChecker::class);
        $this->subject = new AdvancedSearchSettingsService(
            $this->classMetadataSearcher,
            $this->defaultSearchSettingsService,
            $this->advancedSearchChecker
        );
    }

    public function testSearch(): void
    {
        $this->advancedSearchChecker
            ->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->classMetadataSearcher
            ->method('findAll')
            ->willReturn(
                new ClassCollection(
                    ...[
                        (new ClassMetadata())
                            ->setMetaData(
                                new MetadataCollection(
                                    (new Metadata())
                                        ->setLabel('metadataLabel1')
                                        ->setType('list')
                                        ->setPropertyUri('metadataUri1')
                                        ->setSortId('metadataUri1')
                                        ->setClassLabel('metadataClassLabel1')
                                        ->setAlias('metadataAlias1')
                                )
                            )
                    ]
                )
            );

        $result = $this->subject->getSettingsByClassMetadataSearchRequest(
            (new ClassMetadataSearchRequest())->setClassUri('classUri')
        );

        $this->assertSame(
            [
                'availableColumns' => [
                    [
                        'id' => 'label',
                        'sortId' => 'label',
                        'label' => 'Label',
                        'type' => 'text',
                        'alias' => null,
                        'classLabel' => null,
                        'isDuplicated' => false,
                        'default' => true,
                        'sortable' => true,
                    ],
                    [
                        'id' => 'location',
                        'sortId' => 'location',
                        'label' => 'Location',
                        'type' => 'text',
                        'alias' => null,
                        'classLabel' => null,
                        'isDuplicated' => false,
                        'default' => true,
                        'sortable' => true,
                    ],
                    [
                        'id' => 'updated_at',
                        'sortId' => 'updated_at',
                        'label' => 'Last modified on',
                        'type' => 'text',
                        'alias' => null,
                        'classLabel' => null,
                        'isDuplicated' => false,
                        'default' => true,
                        'sortable' => true,
                    ],
                    [
                        'id' => 'metadataUri1',
                        'sortId' => 'metadataUri1',
                        'label' => 'metadataLabel1',
                        'type' => 'list',
                        'alias' => 'metadataAlias1',
                        'classLabel' => 'metadataClassLabel1',
                        'isDuplicated' => false,
                        'default' => false,
                        'sortable' => false,
                    ],
                ]
            ],
            json_decode(json_encode($result->jsonSerialize()), true)
        );
    }

    public function testSearchWhenAdvancedSearchIsDisabled(): void
    {
        $this->advancedSearchChecker
            ->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $searchSettings = new SearchSettings([]);

        $this->defaultSearchSettingsService
            ->method('getSettingsByClassMetadataSearchRequest')
            ->willReturn($searchSettings);

        $this->assertSame(
            $searchSettings,
            $this->subject->getSettingsByClassMetadataSearchRequest(new ClassMetadataSearchRequest())
        );
    }
}
