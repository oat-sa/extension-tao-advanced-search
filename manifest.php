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
 * Copyright (c) 2021-2022 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

use oat\tao\model\accessControl\func\AccessRule;
use oat\taoAdvancedSearch\scripts\install\RegisterEvents;
use oat\taoAdvancedSearch\scripts\install\ActivateSearch;
use oat\taoAdvancedSearch\scripts\install\RegisterServices;
use oat\taoAdvancedSearch\scripts\install\Checks\EndpointCheck;
use oat\taoAdvancedSearch\scripts\install\CreateIndexStructure;
use oat\taoAdvancedSearch\scripts\install\RegisterTaskQueueServices;
use oat\taoAdvancedSearch\scripts\uninstall\UnRegisterTaskQueueServices;
use oat\taoAdvancedSearch\model\Metadata\ServiceProvider\MetadataServiceProvider;

$managerRole = 'http://www.tao.lu/Ontologies/generis.rdf#advancedSearchManager';

return [
    'name' => 'taoAdvancedSearch',
    'label' => 'Extension to manage advanced search',
    'description' => 'Extension to manage advanced search integration for all TAO resources',
    'license' => 'GPL-2.0',
    'author' => 'Open Assessment Technologies SA',
    'managementRole' => $managerRole,
    'acl' => [
        [
            AccessRule::GRANT,
            $managerRole,
            [
                'ext' => 'taoAdvancedSearch',
            ],
        ],
    ],
    'install' => [
        'checks' => [
            [
                'type' => 'CheckCustom',
                'value' => [
                    'name' => EndpointCheck::class,
                    'extension' => 'taoAdvancedSearch',
                ],
            ],
        ],
        'php' => [
            RegisterServices::class,
            RegisterEvents::class,
            RegisterTaskQueueServices::class,
            ActivateSearch::class,
            CreateIndexStructure::class,
        ],
        'rdf' => [],
    ],
    'uninstall' => [
        'php' => [
            UnRegisterTaskQueueServices::class,
        ],
    ],
    'routes' => [
    ],
    'constants' => [
        'BASE_URL' => ROOT_URL . 'taoAdvancedSearch/',
    ],
    'containerServiceProviders' => [
        MetadataServiceProvider::class,
    ],
];
