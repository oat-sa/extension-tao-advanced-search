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
 * Copyright (c) 2021-2022 (original work) Open Assessment Technologies SA;
 */

use oat\taoAdvancedSearch\model\Metadata\ServiceProvider\MetadataServiceProvider;
use oat\taoAdvancedSearch\model\SearchEngine\ServiceProvider\SearchEngineProvider;
use oat\taoAdvancedSearch\scripts\install\RegisterEvents;
use oat\taoAdvancedSearch\scripts\install\RegisterServices;
use oat\taoAdvancedSearch\scripts\install\RegisterTaskQueueServices;
use oat\taoAdvancedSearch\scripts\uninstall\UnRegisterTaskQueueServices;

$managerRole = 'http://www.tao.lu/Ontologies/generis.rdf#advancedSearchManager';

return [
    'name' => 'taoAdvancedSearch',
    'label' => 'Extension to manage advanced search',
    'description' => 'Extension to manage advanced search integration for all TAO resources',
    'license' => 'GPL-2.0',
    'author' => 'Open Assessment Technologies SA',
    'managementRole' => $managerRole,
    'acl' => [
        ['grant', $managerRole, ['ext' => 'taoAdvancedSearch']],
    ],
    'install' => [
        'php' => [
            RegisterServices::class,
            RegisterEvents::class,
            RegisterTaskQueueServices::class,
        ],
        'rdf' => []
    ],
    'uninstall' => [
        'php' => [
            UnRegisterTaskQueueServices::class,
        ]
    ],
    'routes' => [
    ],
    'constants' => [
        'BASE_URL' => ROOT_URL . 'taoAdvancedSearch/',
    ],
    'containerServiceProviders' => [
        MetadataServiceProvider::class,
        SearchEngineProvider::class,
    ]
];
