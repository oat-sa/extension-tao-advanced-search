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

namespace oat\taoAdvancedSearch\model\Index\Service;

use InvalidArgumentException;
use oat\tao\model\task\migration\AbstractMigrationTask;
use oat\tao\model\task\migration\service\MigrationConfigFactory;
use oat\tao\model\task\migration\service\MigrationConfigFactoryInterface;
use oat\tao\model\task\migration\service\ResultFilterFactoryInterface;
use oat\tao\model\task\migration\service\ResultSearcherInterface;
use oat\tao\model\task\migration\service\ResultUnitProcessorInterface;
use oat\tao\model\task\migration\service\SpawnMigrationConfigService;
use oat\tao\model\task\migration\service\SpawnMigrationConfigServiceInterface;
use oat\taoAdvancedSearch\model\Index\Normalizer\NormalizerInterface;

abstract class AbstractIndexMigrationTask extends AbstractMigrationTask
{
    public const OPTION_NORMALIZER = 'normalizer';
    public const OPTION_RESULT_SEARCHER = 'resultSearcher';
    public const OPTION_RESULT_FILTER_FACTORY = 'filterFactory';

    protected function getUnitProcessor(): ResultUnitProcessorInterface
    {
        $indexer = $this->getIndexer()
            ->setNormalizer($this->getNormalizer());

        $unitProcessor = $this->getIndexUnitProcessor();

        return $unitProcessor->setIndexer($indexer);
    }

    protected function getResultSearcher(): ResultSearcherInterface
    {
        return $this->getServiceLocator()->get($this->getConfigValue(self::OPTION_RESULT_SEARCHER));
    }

    protected function getResultFilterFactory(): ResultFilterFactoryInterface
    {
        return $this->getServiceLocator()->get($this->getConfigValue(self::OPTION_RESULT_FILTER_FACTORY));
    }

    protected function getSpawnMigrationConfigService(): SpawnMigrationConfigServiceInterface
    {
        return $this->getServiceLocator()->get(SpawnMigrationConfigService::class);
    }

    protected function getMigrationConfigFactory(): MigrationConfigFactoryInterface
    {
        return $this->getServiceLocator()->get(MigrationConfigFactory::class);
    }

    abstract protected function getConfig(): array;

    private function getConfigValue(string $config)
    {
        $options = $this->getConfig();

        if (isset($options[$config])) {
            return $options[$config];
        }

        throw new InvalidArgumentException(sprintf('Missing config %s', $config));
    }

    private function getIndexUnitProcessor(): IndexUnitProcessor
    {
        return $this->getServiceLocator()->get(IndexUnitProcessor::class);
    }

    private function getIndexer(): ResultIndexer
    {
        return $this->getServiceLocator()->get(ResultIndexer::class);
    }

    private function getNormalizer(): NormalizerInterface
    {
        return $this->getServiceLocator()->get($this->getConfigValue(self::OPTION_NORMALIZER));
    }
}
