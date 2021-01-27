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

namespace oat\taoAdvancedSearch\scripts;

use oat\generis\model\OntologyAwareTrait;
use oat\oatbox\extension\script\ScriptAction;
use oat\oatbox\reporting\Report;
use oat\tao\model\TaoOntology;
use oat\taoAdvancedSearch\model\Cache\PropertyCachingService;
use oat\taoAdvancedSearch\model\Cache\PropertyTreeGenerator;

class InitiateClassPropertyTree extends ScriptAction
{
    use OntologyAwareTrait;

    private const ROOT_CLASSES = [
        TaoOntology::CLASS_URI_ITEM,
        TaoOntology::CLASS_URI_ASSEMBLED_DELIVERY,
        TaoOntology::CLASS_URI_GROUP,
        TaoOntology::CLASS_URI_TEST,
    ];

    protected function provideOptions()
    {
        return [
            'targetRootClass' => [
                'prefix' => 'f',
                'flag' => false,
                'longPrefix' => 'target-root-class',
                'required' => false,
                'description' => 'Force index removal and regenerate tree',
            ],

        ];
    }

    protected function provideDescription()
    {
        return 'This command will initiate elastic search cached property tree for each root class';
    }

    protected function run(): Report
    {
        foreach ($this->getUrisToProcess() as $rootClassUri) {
            $tree = $this->getTreeGenerator()->getClassPropertyTree($rootClassUri);

            try {
                $this->getPropertyCachingService()->indexClassPropertyTree($rootClassUri, $tree);
            } catch (\Exception $exception) {
                return Report::createError(var_export($exception->getMessage(), true));
            }
        }

        return Report::createInfo('done');
    }

    public function getPropertyCachingService(): PropertyCachingService
    {
        return $this->getServiceLocator()->get(PropertyCachingService::class);
    }

    private function getTreeGenerator(): PropertyTreeGenerator
    {
        return $this->getServiceLocator()->get(PropertyTreeGenerator::class);
    }

    private function getUrisToProcess(): array
    {
        if ($this->hasOption('targetRootClass')) {
            return [
                $this->getOption('targetRootClass')
            ];
        }

        return self::ROOT_CLASSES;
    }
}
