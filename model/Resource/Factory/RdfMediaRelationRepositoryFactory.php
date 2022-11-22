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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA.
 */

namespace oat\taoAdvancedSearch\model\Resource\Factory;

use oat\oatbox\service\ServiceManager;
use oat\taoMediaManager\model\relation\repository\MediaRelationRepositoryInterface;
use oat\taoMediaManager\model\relation\repository\rdf\RdfMediaRelationRepository;

/**
 * Facade/Anti-Corruption layer used to inject an instance of the legacy service
 * RdfMediaRelationRepository (based on ConfigurableService) from the DI
 * container service providers.
 */
class RdfMediaRelationRepositoryFactory
{
    public static function getRdfMediaRelationRepository(): ?MediaRelationRepositoryInterface
    {
        if (!class_exists(RdfMediaRelationRepository::class)) {
            return null;
        }

        $builder = new RdfMediaRelationRepository();
        $builder->setServiceManager(ServiceManager::getServiceManager());

        return $builder;
    }
}
