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

use oat\generis\test\TestCase;
use oat\tao\elasticsearch\ElasticSearch;
use oat\tao\model\AdvancedSearch\AdvancedSearchChecker;
use oat\tao\model\Lists\Business\Domain\ClassCollection;
use oat\tao\model\Lists\Business\Domain\ClassMetadataSearchRequest;
use oat\tao\model\Lists\Business\Input\ClassMetadataSearchInput;
use oat\tao\model\Lists\Business\Service\ClassMetadataService;
use oat\taoAdvancedSearch\model\Metadata\Service\ClassMetadataSearcher;

class ClassMetadataSearcherTest extends TestCase
{
    /** @var ClassMetadataSearcher */
    private $subject;

    /** @var ClassMetadataService|\PHPUnit\Framework\MockObject\MockObject */
    private $classMetadataService;

    /** @var AdvancedSearchChecker|\PHPUnit\Framework\MockObject\MockObject */
    private $advancedSearchChecker;

    /** @var ElasticSearch|\PHPUnit\Framework\MockObject\MockObject */
    private $elasticSearch;

    public function setUp(): void
    {
        $this->classMetadataService = $this->createMock(ClassMetadataService::class);
        $this->advancedSearchChecker = $this->createMock(AdvancedSearchChecker::class);
        $this->elasticSearch = $this->createMock(ElasticSearch::class);

        $this->subject = new ClassMetadataSearcher();
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    ClassMetadataService::SERVICE_ID => $this->classMetadataService,
                    AdvancedSearchChecker::class => $this->advancedSearchChecker,
                    ElasticSearch::class => $this->elasticSearch,
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
}

