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

namespace oat\taoAdvancedSearch\tests\Unit\Index\Service;

use InvalidArgumentException;
use oat\generis\test\TestCase;
use oat\tao\model\task\migration\service\MigrationConfigFactory;
use oat\tao\model\task\migration\service\SpawnMigrationConfigService;
use oat\tao\test\unit\helpers\NoPrivacyTrait;
use oat\taoAdvancedSearch\model\DeliveryResult\Factory\DeliveryResultFilterFactory;
use oat\taoAdvancedSearch\model\DeliveryResult\Normalizer\DeliveryResultNormalizer;
use oat\taoAdvancedSearch\model\DeliveryResult\Service\DeliveryResultSearcher;
use oat\taoAdvancedSearch\model\Index\Service\AbstractIndexMigrationTask;
use oat\taoAdvancedSearch\model\Index\Service\IndexUnitProcessor;
use oat\taoAdvancedSearch\model\Index\Service\ResultIndexer;
use PHPUnit\Framework\MockObject\MockObject;

class AbstractIndexMigrationTaskTest extends TestCase
{
    use NoPrivacyTrait;

    /** @var DeliveryResultNormalizer|MockObject */
    private $normalizer;

    /** @var AbstractIndexMigrationTask|MockObject */
    private $subject;

    /** @var MigrationConfigFactory|MockObject */
    private $migrationConfigFactory;

    /** @var IndexUnitProcessor|MockObject */
    private $indexUnitProcessor;

    /** @var SpawnMigrationConfigService|MockObject */
    private $spawnMigrationConfigService;

    /** @var ResultIndexer|MockObject */
    private $resultIndexer;

    /** @var DeliveryResultFilterFactory|MockObject */
    private $deliveryResultFilterFactory;

    /** @var DeliveryResultSearcher|MockObject */
    private $deliveryResultSearcher;

    public function setUp(): void
    {
        $this->normalizer = $this->createMock(DeliveryResultNormalizer::class);
        $this->migrationConfigFactory = $this->createMock(MigrationConfigFactory::class);
        $this->spawnMigrationConfigService = $this->createMock(SpawnMigrationConfigService::class);
        $this->indexUnitProcessor = $this->createMock(IndexUnitProcessor::class);
        $this->resultIndexer = $this->createMock(ResultIndexer::class);
        $this->deliveryResultSearcher = $this->createMock(DeliveryResultSearcher::class);
        $this->deliveryResultFilterFactory = $this->createMock(DeliveryResultFilterFactory::class);

        $this->subject = $this->getMockForAbstractClass(AbstractIndexMigrationTask::class);
        $this->subject->setServiceLocator(
            $this->getServiceLocatorMock(
                [
                    MigrationConfigFactory::class => $this->migrationConfigFactory,
                    SpawnMigrationConfigService::class => $this->spawnMigrationConfigService,
                    IndexUnitProcessor::class => $this->indexUnitProcessor,
                    ResultIndexer::class => $this->resultIndexer,
                    DeliveryResultSearcher::class => $this->deliveryResultSearcher,
                    DeliveryResultFilterFactory::class => $this->deliveryResultFilterFactory,
                    DeliveryResultNormalizer::class => $this->normalizer,
                ]
            )
        );
    }

    public function testGetters(): void
    {
        $this->subject
            ->method('getConfig')
            ->willReturn(
                [
                    AbstractIndexMigrationTask::OPTION_NORMALIZER => DeliveryResultNormalizer::class,
                    AbstractIndexMigrationTask::OPTION_RESULT_SEARCHER => DeliveryResultSearcher::class,
                    AbstractIndexMigrationTask::OPTION_RESULT_FILTER_FACTORY => DeliveryResultFilterFactory::class,
                ]
            );

        $indexer = $this->resultIndexer->setNormalizer($this->normalizer);
        $indexUnitProcessor = $this->indexUnitProcessor->setIndexer($indexer);

        $this->assertEquals(
            $indexUnitProcessor,
            $this->invokePrivateMethod($this->subject, 'getUnitProcessor', [])
        );
        $this->assertEquals(
            $this->deliveryResultSearcher,
            $this->invokePrivateMethod($this->subject, 'getResultSearcher', [])
        );
        $this->assertEquals(
            $this->deliveryResultFilterFactory,
            $this->invokePrivateMethod($this->subject, 'getResultFilterFactory', [])
        );
        $this->assertEquals(
            $this->spawnMigrationConfigService,
            $this->invokePrivateMethod($this->subject, 'getSpawnMigrationConfigService', [])
        );
        $this->assertEquals(
            $this->migrationConfigFactory,
            $this->invokePrivateMethod($this->subject, 'getMigrationConfigFactory', [])
        );
    }

    public function testGetMissingConfigThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf('Missing config %s', AbstractIndexMigrationTask::OPTION_RESULT_FILTER_FACTORY)
        );

        $this->subject
            ->method('getConfig')
            ->willReturn([]);

        $this->invokePrivateMethod($this->subject, 'getResultFilterFactory', []);
    }
}
