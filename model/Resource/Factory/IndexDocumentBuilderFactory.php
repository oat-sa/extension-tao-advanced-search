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
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilder;
use oat\tao\model\search\index\DocumentBuilder\IndexDocumentBuilderInterface;

/**
 * Facade/Anti-Corruption layer used to inject an instance of the legacy service
 * IndexDocumentBuilder (based on InjectionAwareService) from the DI container
 * service providers.
 */
class IndexDocumentBuilderFactory
{
    public static function getIndexDocumentBuilder()
                                                : IndexDocumentBuilderInterface
    {
        $builder = new IndexDocumentBuilder();
        $builder->setServiceManager(ServiceManager::getServiceManager());

        return $builder;
    }
}
