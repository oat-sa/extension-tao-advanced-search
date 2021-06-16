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
use oat\taoAdvancedSearch\model\Resource\Cache\CacheIndexableResourceUrisService;
use oat\taoAdvancedSearch\model\Resource\Repository\IndexableResourceUrisRepository;
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
        //FIXME
        //FIXME Testing
        //FIXME
        /** @var IndexableResourceUrisRepository $aaa */
//        $aaa = $this->getServiceLocator()->get(IndexableResourceUrisRepository::class);
//        $aaa->findAll(0, 100);
//        exit();
        //FIXME
        //FIXME

        $report = Report::createInfo('Warming up cache...');

        $classUriCachedRepository = $this->getClassUriCachedRepository();
        $classUriCachedRepository->cacheWarmup();

        $cacheIndexableResourceUrisService = $this->getCacheIndexableResourceUrisService();
        $cacheIndexableResourceUrisService->warmup();

        $report->add(
            Report::createSuccess(
                sprintf('Cache warmed up! %s classUris in cache', $classUriCachedRepository->getTotal())
            )
        );
        $report->add(
            Report::createSuccess(
                sprintf('Cache warmed up! %s resourceUris in cache', $cacheIndexableResourceUrisService->getTotal())
            )
        );

        return $report;
    }

    private function getClassUriCachedRepository(): ClassUriCachedRepository
    {
        return $this->getServiceLocator()->get(ClassUriCachedRepository::class);
    }

    private function getCacheIndexableResourceUrisService(): CacheIndexableResourceUrisService
    {
        return $this->getServiceLocator()->get(CacheIndexableResourceUrisService::class);
    }
}
