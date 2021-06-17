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

namespace oat\taoAdvancedSearch\scripts\tools;

use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\scripts\tools\MigrationAction;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepository;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepositoryInterface;
use oat\taoAdvancedSearch\model\Resource\Service\ResourceMigrationTask;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class IndexResourcePopulator extends ScriptAction implements ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;

    protected function provideUsage(): array
    {
        return [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Type this option to see the parameters.'
        ];
    }

    protected function provideOptions(): array
    {
        return [
            'offset' => [
                'prefix' => 'o',
                'longPrefix' => 'offset',
                'flag' => false,
                'description' => 'The limit of resources to be processed per class',
                'defaultValue' => 0
            ],
            'limit' => [
                'prefix' => 'l',
                'longPrefix' => 'limit',
                'flag' => false,
                'description' => 'The limit of resources to be processed per class',
                'defaultValue' => 50
            ],
        ];
    }

    protected function provideDescription(): string
    {
        return 'This script index documents on the search engine';
    }

    /**
     * @inheritDoc
     */
    protected function run(): Report
    {
        $report = Report::createInfo('Executing:');
        $limit = (int)$this->getOption('limit');
        $offset = (int)$this->getOption('offset');

        $this->logInfo('starting indexation');

        foreach ($this->getIndexableClassRepository()->findAll() as $class) {
            $migration = new MigrationAction();
            $this->propagate($migration);
            $report->add(
                $migration->__invoke(
                    [
                        '-c', $limit,
                        '-cp', 'start= ' . $offset . '&classUri=' . $class->getUri(),
                        '-t', ResourceMigrationTask::class,
                        '-rp'
                    ]
                )
            );
        }

        return $report;
    }

    private function getIndexableClassRepository(): IndexableClassRepositoryInterface
    {
        return $this->getServiceLocator()->get(IndexableClassRepository::class);
    }
}
