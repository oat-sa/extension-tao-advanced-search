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

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\taoAdvancedSearch\model\Metadata\Repository\ClassUriCachedRepository;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassCachedRepository;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableClassRepositoryInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class CacheWarmup extends ScriptAction implements ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;
    use OntologyAwareTrait;

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
        return [];
    }

    protected function provideDescription(): string
    {
        return 'Warmup cache for indexation';
    }

    /**
     * @inheritDoc
     */
    protected function run(): Report
    {
        $report = Report::createInfo('Warming up cache...');

        $classUriCachedRepository = $this->getClassUriCachedRepository();
        $classUriCachedRepository->cacheWarmup();

        $indexableClassRepository = $this->getIndexableClassRepository();
        $indexableClassRepository->cacheWarmup();

        $report->add(
            Report::createSuccess(
                sprintf('Cache warmed up! %s classUris in cache', $classUriCachedRepository->getTotal())
            )
        );

        $report->add(
            Report::createSuccess(
                sprintf(
                    'Cache warmed up! ROOT classUris (%s) in cache',
                    implode(', ', $indexableClassRepository->findAllUris())
                )
            )
        );

        return $report;
    }

    private function getClassUriCachedRepository(): ClassUriCachedRepository
    {
        return $this->getServiceLocator()->get(ClassUriCachedRepository::class);
    }

    private function getIndexableClassRepository(): IndexableClassRepositoryInterface
    {
        return $this->getServiceLocator()->get(IndexableClassCachedRepository::class);
    }
}
